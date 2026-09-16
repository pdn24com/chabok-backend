<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateConsignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateConsignmentHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentAccessGuard $consignmentAccessGuard,
        private \Modules\Geography\Application\GeographyResolver $geography,
        private \Modules\Consignment\Domain\ConsignmentPolicy $policy,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
        private \Modules\Consignment\Application\PricingService $pricing,
        private \Modules\Consignment\Application\Services\ConsignmentDraft $consignmentDraft,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Consignment\Application\Services\ConsignmentNumberAllocator $numbers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Services\ConsignmentHistoryWriter $consignmentHistoryWriter,
        private \Modules\Consignment\Application\Services\ConsignmentPricingWriter $consignmentPricingWriter,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
        private \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler $getConsignment,
    )
    {
    }

    public function handle(CreateConsignmentCommand $command): CreateConsignmentResult
    {
        return new CreateConsignmentResult($this->execute($command->actor, $command->nodeId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        $context = $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.create');
        $acceptedInput = (array) $input['accepted_quote'];
        unset($input['accepted_quote']);
        foreach (['sender', 'receiver'] as $party) {
            $input[$party] = $this->geography->canonicalizeContact((array) $input[$party], true);
        }
        $this->policy->assertCommercialConsistency($input);
        $this->policy->assertPilotCreate($input);
        if (($input['delivery_node_id'] ?? null) !== null && !$this->consignments->activeNodeExists($input['delivery_node_id'], $actor->hqId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The final delivery node is not active or does not belong to the tenant.');
        }
        $accepted = $this->pricing->accept($actor, $nodeId, 'CREATE', $input, $acceptedInput);
        if (($accepted['provider_code'] ?? null) === 'INTERNAL') {
            $input['service_type_id'] = $accepted['service_type_id'];
            $input['shipping_method_id'] = $accepted['shipping_method_id'];
            $input['service_offering_id'] = $accepted['service_offering_id'];
            $input['service_offering_version_id'] = $accepted['service_offering_version_id'];
            $input['selected_option_version_ids'] = $accepted['selected_option_version_ids'];
            $input = $this->consignmentDraft->withAcceptedCommitment($input, $accepted['commitment'] ?? null);
        } elseif (($input['service_offering_id'] ?? null) !== null) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'Pilot Service Offering creation requires internal versioned Pricing.', details: ['reason_code' => 'INTERNAL_PRICING_REQUIRED']);
        }
        $consignmentId = $this->transactions->run(function () use ($actor, $nodeId, $input, $accepted, $correlationId): string {
            $id = $this->identifiers->uuid();
            $allocation = $this->numbers->next((string) $actor->hqId);
            $number = $allocation['consignment_number'];
            $now = $this->clock->now();
            $parcels = (array) $input['parcels'];
            $this->consignments->insertConsignment([
                'consignment_id' => $id,
                'hq_id' => $actor->hqId,
                'consignment_number' => $number,
                'initiator_id' => $actor->userId,
                'pickup_node_id' => $nodeId,
                'delivery_node_id' => $input['delivery_node_id'] ?? null,
                ...$this->consignmentDraft->contactColumns('sender', (array) $input['sender']),
                ...$this->consignmentDraft->contactColumns('receiver', (array) $input['receiver']),
                ...$this->consignmentDraft->commercialColumns($input),
                'current_status' => 'CFM',
                'aggregate_mode' => 'FULL',
                'parcel_status_counts' => json_encode(['CFM' => count($parcels)], JSON_THROW_ON_ERROR),
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->numbers->record((string) $actor->hqId, $allocation['range_id'], $id, $number, $actor->userId, $correlationId);
            foreach (array_values($parcels) as $index => $parcelInput) {
                $parcelId = $this->identifiers->uuid();
                $this->consignments->insertParcel([
                    'parcel_id' => $parcelId,
                    'hq_id' => $actor->hqId,
                    'consignment_id' => $id,
                    'parcel_number' => sprintf('%s-%02d', $number, $index + 1),
                    'current_status' => 'CFM',
                    'current_node_id' => $nodeId,
                    'current_custody_type' => 'NODE',
                    'current_custodian_id' => $nodeId,
                    'content_description' => trim((string) ($parcelInput['content_description'] ?? '')) ?: null,
                    ...$this->consignmentDraft->parcelPhysical((array) $parcelInput, $input),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->consignments->appendCustodyEvent([
                    'custody_event_id' => $this->identifiers->uuid(),
                    'hq_id' => $actor->hqId,
                    'event_sequence' => $this->consignments->nextCustodySequence($id),
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
                ]);
                $this->consignmentHistoryWriter->insertStatusEvent((string) $actor->hqId, $id, $parcelId, $actor->userId, $nodeId, null, 'CFM', 'CONSIGNMENT_CONFIRMED', $correlationId);
            }
            $this->consignmentHistoryWriter->insertStatusEvent((string) $actor->hqId, $id, null, $actor->userId, $nodeId, null, 'CFM', 'CONSIGNMENT_CONFIRMED', $correlationId);
            $pricingVersionId = $this->consignmentPricingWriter->persistPricing((string) $actor->hqId, $id, 1, $actor->userId, $accepted);
            $this->audit->write($actor->hqId, $actor->userId, 'CONSIGNMENT_CREATED', 'CONSIGNMENT', $id, $correlationId, after: ['version' => 1, 'status' => 'CFM', 'parcel_count' => count($parcels)], sourceClient: 'BRANCH_PANEL');
            $this->audit->write($actor->hqId, $actor->userId, 'CONSIGNMENT_PRICING_ACCEPTED', 'CONSIGNMENT', $id, $correlationId, after: [
                'pricing_version_id' => $pricingVersionId,
                'quote_id' => $accepted['quote_id'],
                'option_id' => $accepted['option_id'],
            ], sourceClient: 'BRANCH_PANEL');
            $this->outbox->write($actor->hqId, 'CONSIGNMENT', $id, 'consignment.created', $correlationId, ['consignment_id' => $id, 'version' => '1', 'status' => 'CFM', 'pricing_version_id' => $pricingVersionId]);
            $this->outbox->write($actor->hqId, 'CONSIGNMENT', $id, 'consignment.pricing.accepted', $correlationId, ['consignment_id' => $id, 'version' => '1', 'pricing_version_id' => $pricingVersionId]);
            return $id;
        });
        $this->pricing->consume((string) $accepted['quote_id']);
        return $this->getConsignment->handle(new \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand($actor, $nodeId, $consignmentId))->data;
    }
}
