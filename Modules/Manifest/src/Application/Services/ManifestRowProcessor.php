<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Consignment\Application\Contracts\ConsignmentAggregateProjectorInterface;
use Modules\Consignment\Application\Dto\AggregateProjectionDto;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestEligibilityEvaluatorInterface;
use Modules\Manifest\Application\Contracts\ManifestParcelWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestRowProcessorInterface;
use Modules\Manifest\Application\Contracts\ManifestTargetApplierInterface;
use Modules\Manifest\Application\Contracts\ManifestTransitionRecorderInterface;
use Modules\Manifest\Application\Dto\ManifestEligibleParcelDto;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Serialization\ManifestEligibilityDocument;
use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Enums\ManifestParcelStatus;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;

final readonly class ManifestRowProcessor implements ManifestRowProcessorInterface
{
    public function __construct(
        private ManifestEligibilityEvaluatorInterface $manifestEligibilityEvaluator,
        private ManifestTargetApplierInterface $manifestTargetApplier,
        private ManifestTransitionRecorderInterface $manifestTransitionRecorder,
        private ClockInterface $clock,
        private ConsignmentAggregateProjectorInterface $consignmentAggregateProjector,
        private ManifestTaskAccessInterface $manifestTaskAccess,
        private ManifestParcelWriterInterface $manifestParcelWriter,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
        private ConsignmentRepositoryInterface $consignmentRepository,
        private ParcelRepositoryInterface $parcelRepository,
    ) {}

    public function applyRows(
        AuthenticatedPrincipal $actor,
        string $node,
        ManifestRecord $m,
        string $correlationId,
        bool $exceptionApproval,
    ): int {
        $rows = $this->manifestParcelRepository->lockRowsWithStatus($actor->hqId, (string) $m->manifest_id, [
            ManifestParcelStatus::Pending->value, ManifestParcelStatus::Validated->value, ManifestParcelStatus::Failed->value,
        ]);
        $parcelIds = $rows->pluck('parcel_id')->all();
        $this->consignmentRepository->lockParentsOfParcels((string) $actor->hqId, $parcelIds);
        $parcels = $this->parcelRepository->lockByIdsKeyedById((string) $actor->hqId, $parcelIds);
        $eligibilities = $this->manifestEligibilityEvaluator->evaluateMany($parcels, $m, $node);
        $transitions = [];
        $success = 0;
        $consignments = [];
        $eligible = [];
        foreach ($rows as $row) {
            $p = $parcels->get($row->parcel_id);
            $e = $eligibilities[$row->parcel_id] ?? ManifestEligibilityReason::ParcelNotFound;
            if (! $e->eligible()) {
                $this->markFailed($row, $e);

                continue;
            }
            $eligible[] = new ManifestEligibleParcelDto($row, $p);
        }
        if (in_array((string) $m->manifest_status, ['NPU', 'NOK'], true) && ! $exceptionApproval) {
            $this->manifestParcelWriter->saveStates($rows);

            return count($eligible);
        }
        $applied = $this->manifestTargetApplier->applyTargets($actor, $node, $m, array_map(static fn (ManifestEligibleParcelDto $item): ParcelRecord => $item->parcel, $eligible), $correlationId);
        foreach ($eligible as $item) {
            $row = $item->row;
            $p = $item->parcel;
            $evidence = $applied[$p->parcel_id]->evidence;
            $transitions[] = $applied[$p->parcel_id];
            $row->forceFill([
                'manifest_parcel_status' => ManifestParcelStatus::Succeeded->value,
                'failure_code' => null,
                'failure_reason' => null,
                'active_slot' => null,
                'source_status' => $p->current_status,
                'origin_node_id' => $evidence->originNodeId,
                'destination_node_id' => $evidence->destinationNodeId,
                'route_plan_id' => $evidence->routePlanId,
                'route_definition_version_id' => $evidence->routeDefinitionVersionId,
                'route_plan_leg_id' => $evidence->routePlanLegId,
                'route_definition_version_leg_id' => $evidence->routeDefinitionVersionLegId,
                'assigned_driver_id' => $evidence->assignedDriverId,
                'assigned_vehicle_id' => $evidence->assignedVehicleId,
                'processed_at' => $this->clock->now(),
                'evidence_recorded_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $success++;
            $consignments[] = (string) $p->consignment_id;
        }
        $this->manifestParcelWriter->saveStates($rows);
        $this->manifestTransitionRecorder->recordTransitions($actor, $node, $m, $transitions, $correlationId);
        $this->consignmentAggregateProjector->projectMany($actor, array_values(array_unique($consignments)), new AggregateProjectionDto(
            (string) $m->manifest_status, $node, (string) $m->manifest_id, 'MANIFEST_AGGREGATE_PROJECTED', $m->assigned_driver_id, $correlationId,
        ));
        if ($success > 0 && $m->manifest_status === 'OS') {
            $this->manifestTaskAccess->assignTenantDriverMission($actor->hqId, $m->assigned_driver_id, ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
            $this->manifestTaskAccess->assignTenantVehicleMission($actor->hqId, $m->assigned_vehicle_id, ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
        }
        if ($success > 0 && in_array((string) $m->manifest_status, ['CI', 'IR'], true) && $m->operational_context_type !== ManifestContextType::PickupReception) {
            $this->manifestTaskAccess->releaseTenantDriverMission($actor->hqId, $m->assigned_driver_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
            $this->manifestTaskAccess->releaseTenantVehicleMission($actor->hqId, $m->assigned_vehicle_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
        }

        return $success;
    }

    public function eligibleRows(
        AuthenticatedPrincipal $actor,
        string $node,
        ManifestRecord $m,
    ): array {
        $out = [];
        $rows = $this->manifestParcelRepository->lockAllRows($actor->hqId, (string) $m->manifest_id);
        $parcelIds = $rows->pluck('parcel_id')->all();
        $this->consignmentRepository->lockParentsOfParcels((string) $actor->hqId, $parcelIds);
        $parcels = $this->parcelRepository->lockByIdsKeyedById((string) $actor->hqId, $parcelIds);
        $eligibilities = $this->manifestEligibilityEvaluator->evaluateMany($parcels, $m, $node);
        foreach ($rows as $row) {
            $p = $parcels->get($row->parcel_id);
            $e = $eligibilities[$row->parcel_id] ?? ManifestEligibilityReason::ParcelNotFound;
            if ($e->eligible()) {
                $out[] = $p;
                $row->forceFill([
                    'manifest_parcel_status' => ManifestParcelStatus::Validated->value,
                    'failure_code' => null,
                    'failure_reason' => null,
                    'processed_at' => null,
                    'updated_at' => $this->clock->now(),
                ]);
            } else {
                $this->markFailed($row, $e);
            }
        }

        $this->manifestParcelWriter->saveStates($rows);

        return $out;
    }

    private function markFailed(ManifestParcelRecord $row, ManifestEligibilityReason $e): void
    {
        $row->forceFill([
            'manifest_parcel_status' => ManifestParcelStatus::Failed->value,
            'failure_code' => $e->value,
            'failure_reason' => ManifestEligibilityDocument::safeReason($e),
            'active_slot' => null,
            'processed_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }
}
