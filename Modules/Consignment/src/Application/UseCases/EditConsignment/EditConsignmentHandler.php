<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\EditConsignment;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Contracts\ConsignmentDraftInterface;
use Modules\Consignment\Application\Contracts\ConsignmentPricingWriterInterface;
use Modules\Consignment\Application\Contracts\ConsignmentSettingsInterface;
use Modules\Consignment\Application\Contracts\EditPricingImpactInterface;
use Modules\Consignment\Application\Contracts\PricingServiceInterface;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentParcelDto;
use Modules\Consignment\Application\Mappers\ConsignmentDraftPatch;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentResult;
use Modules\Consignment\Domain\Policies\ConsignmentPolicy;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Contracts\GeographyResolverInterface;

final readonly class EditConsignmentHandler
{
    public function __construct(
        private ConsignmentAccessGuardInterface $consignmentAccessGuard,
        private ConnectionInterface $connection,
        private ConsignmentRepositoryInterface $consignmentRepository,
        private ConsignmentPolicy $consignmentPolicy,
        private ConsignmentSettingsInterface $consignmentSettings,
        private ConsignmentDraftInterface $consignmentDraft,
        private GeographyResolverInterface $geographyResolver,
        private EditPricingImpactInterface $editPricingImpact,
        private PricingServiceInterface $pricingService,
        private ClockInterface $clock,
        private ConsignmentPricingWriterInterface $consignmentPricingWriter,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private GetConsignmentHandler $getConsignmentHandler,
        private ParcelRepositoryInterface $parcelRepository,
    ) {}

    public function handle(EditConsignmentCommand $command): GetConsignmentResult
    {
        $context = $this->consignmentAccessGuard->assertAccess($command->actor, $command->nodeId, 'consignment.edit');
        $this->connection->transaction(function () use ($command, $context): void {
            $this->editLocked($command, $context);
        }, attempts: 3);
        if ($command->changes->acceptedQuote !== null) {
            $this->pricingService->consume($command->changes->acceptedQuote->quoteId);
        }

        return $this->getConsignmentHandler->handle(new GetConsignmentCommand($command->actor, $command->nodeId, $command->consignmentId));
    }

    private function editLocked(EditConsignmentCommand $command, AccessContextDto $context): void
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $consignmentId = $command->consignmentId;
        $changes = clone $command->changes->changes;
        $correlationId = $command->correlationId;
        $acceptedInput = $command->changes->acceptedQuote;
        $expectedVersion = $command->changes->expectedVersion;
        $changeReason = $command->changes->changeReason;
        $note = $command->changes->note;

        $row = $this->consignmentRepository->lockVisible((string) $actor->hqId, $context->accessibleNodeIds, $consignmentId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        if ((int) $row->version !== $expectedVersion) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'consignment.consignment_changed_since_loaded', details: ['current_version' => (int) $row->version]);
        }
        $this->consignmentPolicy->assertEditable((string) $row->current_status, $this->consignmentSettings->editableStatuses());
        $existing = $row->parcels()->where('hq_id', $actor->hqId)->orderBy('parcel_number')->lockForUpdate()->get();
        $row->setRelation('parcels', $existing);
        $parcelUpdates = $this->parcelChanges($changes, $existing);
        if (in_array('parcels', $changes->presentFields, true)) {
            $changes->parcels = array_values($parcelUpdates);
        }
        $beforeDraft = $this->consignmentDraft->draftFromRow($row);
        $draft = ConsignmentDraftPatch::apply($beforeDraft, $changes);
        $draft->sender = ConsignmentInputMapper::contact($this->geographyResolver->canonicalizeContact(ConsignmentDraftDocument::contact($draft->sender), false));
        $draft->receiver = ConsignmentInputMapper::contact($this->geographyResolver->canonicalizeContact(ConsignmentDraftDocument::contact($draft->receiver), false));
        $this->consignmentPolicy->assertCommercialConsistency($draft);
        $pricingChanged = $this->editPricingImpact->changed($beforeDraft, $draft, $this->editPricingImpact->contactFields($row));
        $accepted = $acceptedInput === null ? null : $this->pricingService->accept($actor, $nodeId, 'EDIT', $draft, $acceptedInput, $consignmentId, $expectedVersion);
        if (($accepted->option->providerCode ?? null) === 'INTERNAL') {
            $draft = $this->consignmentDraft->withAcceptedOffering($draft, $accepted->option);
        }
        $newVersion = $expectedVersion + 1;
        $this->saveParcelChanges($existing, $parcelUpdates);
        $row->forceFill([
            ...$this->consignmentDraft->contactColumns('sender', $draft->sender),
            ...$this->consignmentDraft->contactColumns('receiver', $draft->receiver),
            ...$this->consignmentDraft->commercialColumns($draft),
            ...$accepted === null && $pricingChanged ? ['commercial_pricing_state' => 'STALE'] : [],
            'version' => $newVersion,
            'updated_at' => $this->clock->now(),
        ]);
        $row->save();
        $pricingVersionId = $accepted === null ? null : $this->consignmentPricingWriter->persistPricing($row, $newVersion, $actor->userId, $accepted);
        $this->auditWriter->write($actor->hqId, $actor->userId, 'CONSIGNMENT_UPDATED', 'CONSIGNMENT', $consignmentId, $correlationId, before: ['version' => $expectedVersion, 'status' => $row->current_status], after: [
            'version' => $newVersion,
            'status' => $row->current_status,
            'changed_fields' => $changes->presentFields,
            'parcel_ids' => array_keys($parcelUpdates),
        ], safeNote: $changeReason.($note ? ': '.$note : ''), sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, 'CONSIGNMENT', $consignmentId, 'consignment.updated', $correlationId, array_filter([
            'consignment_id' => $consignmentId,
            'version' => (string) $newVersion,
            'status' => (string) $row->current_status,
            'pricing_version_id' => $pricingVersionId,
        ], static fn ($value) => $value !== null));
        if ($accepted !== null || $pricingChanged) {
            $this->outboxWriter->write($actor->hqId, 'CONSIGNMENT', $consignmentId, $accepted === null ? 'consignment.pricing.stale' : 'consignment.pricing.accepted', $correlationId, array_filter([
                'consignment_id' => $consignmentId,
                'version' => (string) $newVersion,
                'pricing_version_id' => $pricingVersionId,
            ], static fn ($value) => $value !== null));
        }
    }

    /** @param Collection<int, ParcelRecord> $existing @return array<string, ConsignmentParcelDto> */
    private function parcelChanges(ConsignmentDraftDto $changes, Collection $existing): array
    {
        if (! in_array('parcels', $changes->presentFields, true)) {
            return [];
        }
        $incoming = [];
        foreach ($changes->parcels as $parcelChange) {
            $incoming[$parcelChange->parcelId] = $parcelChange;
        }
        if (count($incoming) !== count($changes->parcels) || count($incoming) !== count($existing) || $existing->contains(fn ($parcel) => ! array_key_exists($parcel->parcel_id, $incoming))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'consignment.parcel_references_must_match_existing_consignment');
        }
        $updates = [];
        foreach ($existing as $parcel) {
            $change = clone $incoming[$parcel->parcel_id];
            $dimensions = array_filter([$change->widthCm, $change->lengthCm, $change->heightCm], fn ($value) => $value !== null);
            if (count($dimensions) !== 0 && (count($dimensions) !== 3 || min($dimensions) <= 0)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'consignment.parcel_dimensions_must_all_be_positive_absent');
            }
            $change->parcelId = null;
            $change->presentFields = array_values(array_intersect($change->presentFields, ['content_description', 'weight_kg', 'width_cm', 'length_cm', 'height_cm']));
            $updates[$parcel->parcel_id] = $change;
        }

        return $updates;
    }

    /** @param Collection<int, ParcelRecord> $existing @param array<string, ConsignmentParcelDto> $parcelUpdates */
    private function saveParcelChanges(Collection $existing, array $parcelUpdates): void
    {
        $updatedParcels = [];
        foreach ($existing as $parcel) {
            if (isset($parcelUpdates[$parcel->parcel_id])) {
                $parcel->forceFill([...ConsignmentDraftDocument::parcel($parcelUpdates[$parcel->parcel_id]), 'updated_at' => $this->clock->now()]);
                $updatedParcels[] = $parcel->getAttributes();
            }
        }
        // All rows already exist and are locked; only physical fields are updated.
        foreach (array_chunk($updatedParcels, 100) as $batch) {
            $this->parcelRepository->upsertMeasurements($batch);
        }
    }
}
