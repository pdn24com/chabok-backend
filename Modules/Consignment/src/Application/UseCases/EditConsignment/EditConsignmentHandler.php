<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\EditConsignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class EditConsignmentHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentAccessGuard $consignmentAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
        private \Modules\Consignment\Domain\ConsignmentPolicy $policy,
        private \Modules\Consignment\Application\Contracts\ConsignmentSettings $settings,
        private \Modules\Consignment\Application\Services\ConsignmentDraft $consignmentDraft,
        private \Modules\Geography\Application\GeographyResolver $geography,
        private \Modules\Consignment\Application\EditPricingImpact $editImpact,
        private \Modules\Consignment\Application\PricingService $pricing,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Services\ConsignmentPricingWriter $consignmentPricingWriter,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
        private \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler $getConsignment,
    )
    {
    }

    public function handle(EditConsignmentCommand $command): EditConsignmentResult
    {
        return new EditConsignmentResult($this->execute($command->actor, $command->nodeId, $command->consignmentId, $command->changes, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        array $changes,
        string $correlationId,
    ): array
    {
        $context = $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.edit');
        $acceptedInput = isset($changes['accepted_quote']) ? (array) $changes['accepted_quote'] : null;
        $expectedVersion = (int) $changes['expected_version'];
        $changeReason = (string) $changes['change_reason'];
        $note = $changes['note'] ?? null;
        unset($changes['accepted_quote'], $changes['expected_version'], $changes['change_reason'], $changes['note']);
        $acceptedQuoteId = $acceptedInput === null ? null : (string) $acceptedInput['quote_id'];
        $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $changes, $expectedVersion, $acceptedInput, $changeReason, $note, $correlationId, $context): void {
            $row = $this->consignments->lockVisible((string) $actor->hqId, $context['accessible_node_ids'], $consignmentId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ((int) $row->version !== $expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Consignment changed since it was loaded.', details: ['current_version' => (int) $row->version]);
            }
            $this->policy->assertEditable((string) $row->current_status, $this->settings->editableStatuses());
            $parcelUpdates = [];
            if (isset($changes['parcels'])) {
                $existing = $this->consignments->lockParcels($actor->hqId, $consignmentId);
                $incoming = array_column($changes['parcels'], null, 'parcel_id');
                if (count($incoming) !== count($changes['parcels']) || count($incoming) !== count($existing) || array_filter($existing, fn($parcel) => !array_key_exists($parcel->parcel_id, $incoming)) !== []) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Parcel references must match the existing consignment.');
                }
                foreach ($existing as $parcel) {
                    $fields = array_intersect_key($incoming[$parcel->parcel_id], array_flip(['content_description', 'weight_kg', 'width_cm', 'length_cm', 'height_cm']));
                    $dimensions = array_filter([$fields['width_cm'] ?? null, $fields['length_cm'] ?? null, $fields['height_cm'] ?? null], fn($value) => $value !== null);
                    if (count($dimensions) !== 0 && (count($dimensions) !== 3 || min($dimensions) <= 0)) {
                        throw new ApiException(ApiErrorCode::ValidationError, 422, 'Parcel dimensions must all be positive or absent.');
                    }
                    $parcelUpdates[$parcel->parcel_id] = $fields;
                }
                $changes['parcels'] = array_values($parcelUpdates);
            }
            $beforeDraft = $this->consignmentDraft->draftFromRow((array) $row);
            $draft = array_replace_recursive($beforeDraft, $changes);
            foreach (['sender', 'receiver'] as $party) {
                $draft[$party] = $this->geography->canonicalizeContact((array) $draft[$party], false);
            }
            $this->policy->assertCommercialConsistency($draft);
            $pricingChanged = $this->editImpact->changed($beforeDraft, $draft, $this->editImpact->contactFields((array) $row));
            $accepted = $acceptedInput === null ? null : $this->pricing->accept($actor, $nodeId, 'EDIT', $draft, $acceptedInput, $consignmentId, $expectedVersion);
            if (($accepted['provider_code'] ?? null) === 'INTERNAL') {
                $draft['service_type_id'] = $accepted['service_type_id'];
                $draft['shipping_method_id'] = $accepted['shipping_method_id'];
                $draft['service_offering_id'] = $accepted['service_offering_id'];
                $draft['service_offering_version_id'] = $accepted['service_offering_version_id'];
                $draft['selected_option_version_ids'] = $accepted['selected_option_version_ids'];
                $draft = $this->consignmentDraft->withAcceptedCommitment($draft, $accepted['commitment'] ?? null);
            }
            $newVersion = $expectedVersion + 1;
            foreach ($parcelUpdates as $parcelId => $fields) {
                $this->consignments->updateParcel($actor->hqId, $consignmentId, $parcelId, [...$fields, 'updated_at' => $this->clock->now()]);
            }
            $this->consignments->updateConsignmentVersion($actor->hqId, $consignmentId, $expectedVersion, [
                ...$this->consignmentDraft->contactColumns('sender', (array) $draft['sender']),
                ...$this->consignmentDraft->contactColumns('receiver', (array) $draft['receiver']),
                ...$this->consignmentDraft->commercialColumns($draft),
                ...$accepted === null && $pricingChanged ? ['commercial_pricing_state' => 'STALE'] : [],
                'version' => $newVersion,
                'updated_at' => $this->clock->now(),
            ]);
            $pricingVersionId = $accepted === null ? null : $this->consignmentPricingWriter->persistPricing((string) $actor->hqId, $consignmentId, $newVersion, $actor->userId, $accepted);
            $this->audit->write($actor->hqId, $actor->userId, 'CONSIGNMENT_UPDATED', 'CONSIGNMENT', $consignmentId, $correlationId, before: ['version' => $expectedVersion, 'status' => $row->current_status], after: [
                'version' => $newVersion,
                'status' => $row->current_status,
                'changed_fields' => array_keys($changes),
                'parcel_ids' => array_keys($parcelUpdates),
            ], safeNote: $changeReason . ($note ? ': ' . $note : ''), sourceClient: 'BRANCH_PANEL');
            $this->outbox->write($actor->hqId, 'CONSIGNMENT', $consignmentId, 'consignment.updated', $correlationId, array_filter([
                'consignment_id' => $consignmentId,
                'version' => (string) $newVersion,
                'status' => (string) $row->current_status,
                'pricing_version_id' => $pricingVersionId,
            ], static fn($value) => $value !== null));
            if ($accepted !== null || $pricingChanged) {
                $this->outbox->write($actor->hqId, 'CONSIGNMENT', $consignmentId, $accepted === null ? 'consignment.pricing.stale' : 'consignment.pricing.accepted', $correlationId, array_filter([
                    'consignment_id' => $consignmentId,
                    'version' => (string) $newVersion,
                    'pricing_version_id' => $pricingVersionId,
                ], static fn($value) => $value !== null));
            }
        });
        if ($acceptedQuoteId !== null) {
            $this->pricing->consume($acceptedQuoteId);
        }
        return $this->getConsignment->handle(new \Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand($actor, $nodeId, $consignmentId))->data;
    }
}
