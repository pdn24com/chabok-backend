<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateConsignment;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Contracts\ConsignmentDraftInterface;
use Modules\Consignment\Application\Contracts\ConsignmentNumberAllocatorInterface;
use Modules\Consignment\Application\Contracts\ConsignmentPricingWriterInterface;
use Modules\Consignment\Application\Contracts\PricingServiceInterface;
use Modules\Consignment\Application\Dto\AcceptedConsignmentQuoteDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Application\Repositories\CustodyEventRepositoryInterface;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Consignment\Application\Repositories\StatusEventRepositoryInterface;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentResult;
use Modules\Consignment\Domain\Enums\AggregateMode;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Domain\Enums\CustodyType;
use Modules\Consignment\Domain\Policies\ConsignmentPolicy;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Geography\Application\Contracts\GeographyResolverInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class CreateConsignmentHandler
{
    public function __construct(
        private ConsignmentAccessGuardInterface $consignmentAccessGuard,
        private GeographyResolverInterface $geographyResolver,
        private ConsignmentPolicy $consignmentPolicy,
        private PricingServiceInterface $pricingService,
        private ConsignmentDraftInterface $consignmentDraft,
        private ConnectionInterface $connection,
        private ConsignmentNumberAllocatorInterface $consignmentNumberAllocator,
        private ClockInterface $clock,
        private ConsignmentPricingWriterInterface $consignmentPricingWriter,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private GetConsignmentHandler $getConsignmentHandler,
        private NodeRepositoryInterface $nodeRepository,
        private ParcelRepositoryInterface $parcelRepository,
        private CustodyEventRepositoryInterface $custodyEventRepository,
        private StatusEventRepositoryInterface $statusEventRepository,
    ) {}

    public function handle(CreateConsignmentCommand $command): GetConsignmentResult
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $input = clone $command->input->draft;
        $correlationId = $command->correlationId;
        $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.create');
        $acceptedInput = $command->input->acceptedQuote;
        $input->sender = ConsignmentInputMapper::contact($this->geographyResolver->canonicalizeContact(ConsignmentDraftDocument::contact($input->sender), true));
        $input->receiver = ConsignmentInputMapper::contact($this->geographyResolver->canonicalizeContact(ConsignmentDraftDocument::contact($input->receiver), true));
        $this->consignmentPolicy->assertCommercialConsistency($input);
        $this->consignmentPolicy->assertPilotCreate($input);
        if (($input->deliveryNodeId ?? null) !== null && ! $this->nodeRepository->activeExists((string) $actor->hqId, (string) $input->deliveryNodeId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'consignment.final_delivery_node_is_not_active_does');
        }
        $accepted = $this->pricingService->accept($actor, $nodeId, 'CREATE', $input, $acceptedInput);
        $input = $this->applyAcceptedOffering($input, $accepted);
        $consignmentId = $this->connection->transaction(function () use ($actor, $nodeId, $input, $accepted, $correlationId): string {
            return $this->createConsignment($actor, $nodeId, $input, $accepted, $correlationId);
        }, attempts: 3);
        $this->pricingService->consume((string) $accepted->quoteId);

        return $this->getConsignmentHandler->handle(new GetConsignmentCommand($actor, $nodeId, $consignmentId));
    }

    private function applyAcceptedOffering(ConsignmentDraftDto $input, AcceptedConsignmentQuoteDto $accepted): ConsignmentDraftDto
    {
        if (($accepted->option->providerCode ?? null) === 'INTERNAL') {
            $input = $this->consignmentDraft->withAcceptedOffering($input, $accepted->option);
        } elseif (($input->serviceOfferingId ?? null) !== null) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'consignment.pilot_service_offering_creation_requires_internal_versioned', details: ['reason_code' => 'INTERNAL_PRICING_REQUIRED']);
        }

        return $input;
    }

    private function createConsignment(AuthenticatedPrincipal $actor, string $nodeId, ConsignmentDraftDto $input, AcceptedConsignmentQuoteDto $accepted, string $correlationId): string
    {
        $allocation = $this->consignmentNumberAllocator->next((string) $actor->hqId);
        $number = $allocation->consignmentNumber;
        $now = $this->clock->now();
        $parcels = $input->parcels;
        $consignment = new ConsignmentRecord;
        $consignment->forceFill([

            'hq_id' => $actor->hqId,
            'consignment_number' => $number,
            'initiator_id' => $actor->userId,
            'pickup_node_id' => $nodeId,
            'delivery_node_id' => $input->deliveryNodeId ?? null,
            ...$this->consignmentDraft->contactColumns('sender', $input->sender),
            ...$this->consignmentDraft->contactColumns('receiver', $input->receiver),
            ...$this->consignmentDraft->commercialColumns($input),
            'current_status' => ConsignmentStatus::Confirmed->value,
            'aggregate_mode' => AggregateMode::Full->value,
            'parcel_status_counts' => [ConsignmentStatus::Confirmed->value => count($parcels)],
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $consignment->save();
        $id = (string) $consignment->getKey();
        $this->consignmentNumberAllocator->record((string) $actor->hqId, $allocation->rangeId, $id, $number, $actor->userId, $correlationId);
        $this->createParcels($actor, $nodeId, $input, $consignment, $correlationId, $now);
        $pricingVersionId = $this->consignmentPricingWriter->persistPricing($consignment, 1, $actor->userId, $accepted);
        $this->recordCreation($actor, $id, count($parcels), $pricingVersionId, $accepted, $correlationId);

        return $id;
    }

    private function createParcels(AuthenticatedPrincipal $actor, string $nodeId, ConsignmentDraftDto $input, ConsignmentRecord $consignment, string $correlationId, DateTimeImmutable $now): void
    {
        $id = $consignment->consignment_id;
        $number = $consignment->consignment_number;
        $parcels = $input->parcels;
        $parcelRows = $custodyEvents = $statusEvents = [];
        foreach (array_values($parcels) as $index => $parcelInput) {
            $parcelId = sprintf('%s-%02d', $number, $index + 1);
            $parcelRows[] = [
                'hq_id' => $actor->hqId,
                'consignment_id' => $id,
                'parcel_number' => sprintf('%s-%02d', $number, $index + 1),
                'current_status' => ConsignmentStatus::Confirmed->value,
                'current_node_id' => $nodeId,
                'current_custody_type' => CustodyType::Node->value,
                'current_custodian_id' => $nodeId,
                'content_description' => trim((string) ($parcelInput->contentDescription ?? '')) ?: null,
                ...$this->consignmentDraft->parcelPhysical($parcelInput, $input),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $custodyEvents[] = [

                'hq_id' => $actor->hqId,
                'event_sequence' => $index + 1,
                'consignment_id' => $id,
                'parcel_id' => $parcelId,
                'from_node_id' => null,
                'to_node_id' => $nodeId,
                'from_custody_type' => null,
                'to_custody_type' => 'NODE',
                'from_custodian_id' => null,
                'to_custodian_id' => $nodeId,
                'command_name' => 'CONSIGNMENT_CREATED',
                'initiator_id' => $actor->userId,
                'created_at' => $now,
            ];
            $statusEvents[] = [

                'event_sequence' => $index + 1,
                'hq_id' => $actor->hqId,
                'consignment_id' => $id,
                'parcel_id' => $parcelId,
                'previous_status' => null,
                'new_status' => ConsignmentStatus::Confirmed->value,
                'initiator_id' => $actor->userId,
                'node_id' => $nodeId,
                'correlation_id' => $correlationId,
                'reason_code' => 'CONSIGNMENT_CONFIRMED',
                'created_at' => $now,
            ];
        }
        // This consignment is new and locked by its insert; sequence starts at one.
        $statusEvents[] = [

            'event_sequence' => count($parcels) + 1,
            'hq_id' => $actor->hqId,
            'consignment_id' => $id,
            'parcel_id' => null,
            'previous_status' => null,
            'new_status' => ConsignmentStatus::Confirmed->value,
            'initiator_id' => $actor->userId,
            'node_id' => $nodeId,
            'correlation_id' => $correlationId,
            'reason_code' => 'CONSIGNMENT_CONFIRMED',
            'created_at' => $now,
        ];
        foreach (array_chunk($parcelRows, 100) as $batch) {
            $this->parcelRepository->insert($batch);
        }
        $parcelIds = $this->parcelRepository->idsByNumber((string) $actor->hqId, (string) $id);
        foreach ($custodyEvents as &$event) {
            $event['parcel_id'] = $parcelIds[$event['parcel_id']];
        }
        unset($event);
        foreach ($statusEvents as &$event) {
            if ($event['parcel_id'] !== null) {
                $event['parcel_id'] = $parcelIds[$event['parcel_id']];
            }
        }
        unset($event);
        foreach (array_chunk($custodyEvents, 100) as $batch) {
            $this->custodyEventRepository->insert($batch);
        }
        foreach (array_chunk($statusEvents, 100) as $batch) {
            $this->statusEventRepository->insert($batch);
        }
    }

    private function recordCreation(AuthenticatedPrincipal $actor, string $id, int $parcelCount, string $pricingVersionId, AcceptedConsignmentQuoteDto $accepted, string $correlationId): void
    {
        $this->auditWriter->write($actor->hqId, $actor->userId, 'CONSIGNMENT_CREATED', 'CONSIGNMENT', $id, $correlationId, after: [
            'version' => 1,
            'status' => ConsignmentStatus::Confirmed->value,
            'parcel_count' => $parcelCount,
        ], sourceClient: SourceClient::BranchPanel->value);
        $this->auditWriter->write($actor->hqId, $actor->userId, 'CONSIGNMENT_PRICING_ACCEPTED', 'CONSIGNMENT', $id, $correlationId, after: [
            'pricing_version_id' => $pricingVersionId,
            'quote_id' => $accepted->quoteId,
            'option_id' => $accepted->option->optionId,
        ], sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, 'CONSIGNMENT', $id, 'consignment.created', $correlationId, [
            'consignment_id' => $id,
            'version' => '1',
            'status' => ConsignmentStatus::Confirmed->value,
            'pricing_version_id' => $pricingVersionId,
        ]);
        $this->outboxWriter->write($actor->hqId, 'CONSIGNMENT', $id, 'consignment.pricing.accepted', $correlationId, [
            'consignment_id' => $id,
            'version' => '1',
            'pricing_version_id' => $pricingVersionId,
        ]);
    }
}
