<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Manifest\Domain\ManifestEligibilityReason as Reason;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestRowProcessor
{
    public function __construct(
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Consignment\Application\Contracts\ManifestConsignmentAccess $consignmentState,
        private \Modules\Manifest\Application\ManifestEligibilityEvaluator $eligibility,
        private \Modules\Manifest\Application\Services\ManifestTargetApplier $manifestTargetApplier,
        private \Modules\Manifest\Application\Services\ManifestTransitionRecorder $manifestTransitionRecorder,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\ConsignmentAggregateProjector $aggregates,
        private \Modules\Operations\Application\Contracts\ManifestTaskAccess $taskState,
    )
    {
    }

    public function applyRows(
        AuthenticatedPrincipal $actor,
        string $node,
        object $m,
        string $correlationId,
        bool $exceptionApproval,
    ): int
    {
        $rows = $this->workflow->lockProcessableRows($actor->hqId, $m->manifest_id);
        $success = 0;
        $consignments = [];
        $eligible = [];
        foreach ($rows as $row) {
            $p = $this->consignmentState->lockParcel($actor->hqId, $row->parcel_id);
            $e = $p ? $this->eligibility->evaluate($p, $m, $node) : Reason::metadata(Reason::ParcelNotFound);
            if (!$e['eligible']) {
                $this->failRow($row, $e);
                continue;
            }
            $eligible[] = ['row' => $row, 'parcel' => $p];
        }
        if (in_array((string) $m->manifest_status, ['NPU', 'NOK'], true) && !$exceptionApproval) {
            return count($eligible);
        }
        foreach ($eligible as $item) {
            $row = $item['row'];
            $p = $item['parcel'];
            $evidence = $this->manifestTargetApplier->applyTarget($actor, $node, $m, $p, $correlationId);
            $this->manifestTransitionRecorder->recordTransition($actor, $node, $m, $p, $evidence, $correlationId);
            $this->workflow->updateManifestParcel($row->manifest_parcel_id, [
                'manifest_parcel_status' => 'SUCCEEDED',
                'failure_code' => null,
                'failure_reason' => null,
                'active_slot' => null,
                'source_status' => $p->current_status,
                'origin_node_id' => $evidence['origin_node_id'],
                'destination_node_id' => $evidence['destination_node_id'],
                'route_plan_id' => $evidence['route_plan_id'],
                'route_definition_version_id' => $evidence['route_definition_version_id'],
                'route_plan_leg_id' => $evidence['route_plan_leg_id'],
                'route_definition_version_leg_id' => $evidence['route_definition_version_leg_id'],
                'assigned_driver_id' => $evidence['assigned_driver_id'],
                'assigned_vehicle_id' => $evidence['assigned_vehicle_id'],
                'processed_at' => $this->clock->now(),
                'evidence_recorded_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $success++;
            $consignments[] = (string) $p->consignment_id;
        }
        foreach (array_unique($consignments) as $consignment) {
            $this->aggregates->project($actor, $consignment, (string) $m->manifest_status, $node, (string) $m->manifest_id, 'MANIFEST_AGGREGATE_PROJECTED', $m->assigned_driver_id, $correlationId);
        }
        if ($success > 0 && $m->manifest_status === 'OS') {
            $this->taskState->assignTenantDriverMission($actor->hqId, $m->assigned_driver_id, ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
            $this->taskState->assignTenantVehicleMission($actor->hqId, $m->assigned_vehicle_id, ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
        }
        if ($success > 0 && in_array((string) $m->manifest_status, ['CI', 'IR'], true) && $m->operational_context_type !== 'PICKUP_RECEPTION') {
            $this->taskState->releaseTenantDriverMission($actor->hqId, $m->assigned_driver_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
            $this->taskState->releaseTenantVehicleMission($actor->hqId, $m->assigned_vehicle_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
        }
        return $success;
    }

    public function eligibleRows(AuthenticatedPrincipal $actor, string $node, object $m): array
    {
        $out = [];
        foreach ($this->workflow->lockRows($actor->hqId, $m->manifest_id) as $row) {
            $p = $this->consignmentState->lockParcel($actor->hqId, $row->parcel_id);
            $e = $p ? $this->eligibility->evaluate($p, $m, $node) : Reason::metadata(Reason::ParcelNotFound);
            if ($e['eligible']) {
                $out[] = $p;
                $this->workflow->updateManifestParcel($row->manifest_parcel_id, [
                    'manifest_parcel_status' => 'VALIDATED',
                    'failure_code' => null,
                    'failure_reason' => null,
                    'processed_at' => null,
                    'updated_at' => $this->clock->now(),
                ]);
            } else {
                $this->failRow($row, $e);
            }
        }
        return $out;
    }

    public function failRow(object $row, array $e): void
    {
        $this->workflow->updateManifestParcel($row->manifest_parcel_id, [
            'manifest_parcel_status' => 'FAILED',
            'failure_code' => $e['reason_code'],
            'failure_reason' => $e['presentation']['detail']['en'],
            'active_slot' => null,
            'processed_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }
}
