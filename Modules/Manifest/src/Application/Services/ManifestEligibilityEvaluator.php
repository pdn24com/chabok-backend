<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Manifest\Application\Contracts\ManifestEligibilityEvaluatorInterface;
use Modules\Manifest\Application\Dto\ManifestCandidateScopeDto;
use Modules\Manifest\Application\Dto\ManifestEligibilityEvidenceDto;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Policies\ManifestPolicy;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;
use Modules\Operations\Domain\Enums\DeliveryTaskStatus;
use Modules\Operations\Domain\Enums\PickupTaskStatus;

final readonly class ManifestEligibilityEvaluator implements ManifestEligibilityEvaluatorInterface
{
    public function __construct(
        private ManifestPolicy $manifestPolicy,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
        private ManifestTaskAccessInterface $manifestTaskAccess,
        private ManifestRouteAccessInterface $manifestRouteAccess,
    ) {}

    /**
     * @param  Collection<int, ParcelRecord>  $parcels
     * @return array<string, ManifestEligibilityReason>
     */
    public function evaluateMany(Collection $parcels, ManifestRecord $manifest, string $nodeId): array
    {
        if ($parcels->isEmpty()) {
            return [];
        }
        $parcels->loadMissing('consignment');
        $consignments = new Collection($parcels->pluck('consignment')->filter()->all());
        $consignments = $consignments->where('hq_id', $manifest->hq_id)->keyBy('consignment_id');
        $target = (string) $manifest->manifest_status;
        $pickups = new Collection;
        $deliveries = new Collection;
        $sourceRows = new Collection;
        $legs = new Collection;
        if (in_array($target, ['PU', 'NPU', 'IR'], true)) {
            $pickups = $this->manifestTaskAccess->pickupsByConsignment($manifest->hq_id, $nodeId, $consignments->keys()->all());
        }
        if (in_array($target, ['OK', 'NOK'], true)) {
            $deliveries = $this->manifestTaskAccess->deliveriesByConsignment($manifest->hq_id, $nodeId, $consignments->keys()->all());
        }
        if (in_array($target, ['OS', 'IR', 'CI'], true) && $manifest->source_manifest_id !== null) {
            $sourceRows = $this->manifestParcelRepository->succeededSourceRows($manifest->hq_id, $manifest->source_manifest_id, $parcels->pluck('parcel_id')->all());
        }
        if (in_array($target, ['OF', 'OS', 'IR', 'CI', 'OD'], true)) {
            $planIds = $parcels->pluck('active_route_plan_id')->merge($sourceRows->pluck('route_plan_id'))->push($manifest->route_plan_id)->filter()->unique();
            $legs = $this->manifestRouteAccess->legsForPlansAndLeg($manifest->hq_id, $planIds->values()->all(), $manifest->route_plan_leg_id);
        }
        $evidence = new ManifestEligibilityEvidenceDto($consignments, $pickups, $deliveries, $sourceRows, $legs);
        $results = [];
        foreach ($parcels as $parcel) {
            $results[$parcel->parcel_id] = $this->evaluate($parcel, $manifest, $nodeId, $evidence);
        }

        return $results;
    }

    public function candidateScope(ManifestRecord $manifest, string $nodeId): ManifestCandidateScopeDto
    {
        $target = (string) $manifest->manifest_status;
        $movementReception = in_array($target, ['IR', 'CI'], true) && $manifest->operational_context_type !== ManifestContextType::PickupReception;
        $custody = $movementReception ? 'LINEHAUL_DRIVER' : (in_array($target, ['PU', 'NPU'], true) ? 'PICKUP_DRIVER' : (in_array($target, ['OK', 'NOK'], true) ? 'DELIVERY_DRIVER' : null));

        return new ManifestCandidateScopeDto(
            sourceStatuses: $this->manifestPolicy->sourceStatuses($target),
            requiresSourceManifest: $movementReception || $target === 'OS',
            sourceManifestId: $movementReception || $target === 'OS' ? $manifest->source_manifest_id : null,
            custodyType: $custody,
            nodeId: $custody === null ? $nodeId : null,
        );
    }

    private function evaluate(
        ParcelRecord $parcel,
        ManifestRecord $manifest,
        string $nodeId,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        $consignment = $evidence->consignments->get($parcel->consignment_id);
        if ($consignment === null) {
            return ManifestEligibilityReason::ParcelNotFound;
        }
        if ($consignment->service_offering_id !== null && $consignment->commercial_pricing_state !== 'LOCKED') {
            return ManifestEligibilityReason::PricingStale;
        }
        if (! $this->manifestPolicy->canTransition((string) $parcel->current_status, (string) $manifest->manifest_status)) {
            return ManifestEligibilityReason::StatusNotAllowed;
        }

        return match ((string) $manifest->manifest_status) {
            'PD' => $this->nodeCustody($parcel, $consignment->pickup_node_id, $nodeId),
            'PU', 'NPU' => $this->pickupAssignment($parcel, $manifest, $nodeId, $evidence),
            'IR' => (string) $parcel->current_status === 'OS' ? $this->movementReception($parcel, $manifest, $nodeId, 'IR', $evidence) : $this->pickupReception($parcel, $manifest, $nodeId, $evidence),
            'ROU' => $this->nodeCustody($parcel, $nodeId, $nodeId),
            'OF' => $this->outbound($parcel, $manifest, $nodeId, $evidence),
            'OS' => $this->departure($parcel, $manifest, $nodeId, $evidence),
            'CI' => $this->movementReception($parcel, $manifest, $nodeId, 'CI', $evidence),
            'OD' => $this->deliveryAssignment($parcel, $manifest, $consignment, $nodeId, $evidence),
            'OK', 'NOK' => $this->deliveryCompletion($parcel, $manifest, $nodeId, $evidence),
            default => ManifestEligibilityReason::StatusNotAllowed,
        };
    }

    private function nodeCustody(
        ParcelRecord $p,
        ?string $requiredNode,
        string $node,
    ): ManifestEligibilityReason {
        if ((string) $requiredNode !== $node || (string) $p->current_node_id !== $node) {
            return ManifestEligibilityReason::CurrentNodeMismatch;
        }
        if ((string) $p->current_custody_type !== 'NODE' || (string) $p->current_custodian_id !== $node) {
            return ManifestEligibilityReason::CustodyMismatch;
        }

        return ManifestEligibilityReason::Eligible;
    }

    private function pickupAssignment(
        ParcelRecord $p,
        ManifestRecord $m,
        string $node,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        if ((string) $p->current_custody_type !== 'PICKUP_DRIVER' || (string) $p->current_custodian_id !== (string) $m->assigned_driver_id) {
            return ManifestEligibilityReason::PickupAssignmentMismatch;
        }
        $ok = ($task = $evidence->pickups->get($p->consignment_id)) !== null
            && $task->assigned_driver_id === $m->assigned_driver_id
            && in_array($task->status, [PickupTaskStatus::Assigned, PickupTaskStatus::InProgress, PickupTaskStatus::Completed], true);

        return $ok ? ManifestEligibilityReason::Eligible : ManifestEligibilityReason::PickupAssignmentMismatch;
    }

    private function pickupReception(
        ParcelRecord $p,
        ManifestRecord $m,
        string $node,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        if ((string) $p->current_custody_type !== 'PICKUP_DRIVER' || $p->current_custodian_id === null) {
            return ManifestEligibilityReason::CustodyMismatch;
        }
        $ok = ($task = $evidence->pickups->get($p->consignment_id)) !== null
            && $task->assigned_driver_id === $p->current_custodian_id && $task->status === PickupTaskStatus::Completed;

        return $ok ? ManifestEligibilityReason::Eligible : ManifestEligibilityReason::PickupTaskNotCompleted;
    }

    private function movementReception(
        ParcelRecord $p,
        ManifestRecord $m,
        string $node,
        string $target,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        if ((string) $p->current_custody_type !== 'LINEHAUL_DRIVER' || (string) $p->current_custodian_id !== (string) $m->assigned_driver_id) {
            return ManifestEligibilityReason::CustodyMismatch;
        }
        $e = $evidence->sourceRows->get($p->parcel_id);
        if ($e === null || (string) $e->destination_node_id !== $node || (string) $e->assigned_driver_id !== (string) $m->assigned_driver_id || (string) $e->assigned_vehicle_id !== (string) $m->assigned_vehicle_id) {
            return ManifestEligibilityReason::PreviousMovementMismatch;
        }
        $leg = $evidence->legs->get($e->route_plan_leg_id);
        if ($leg === null || $leg->route_plan_id !== $e->route_plan_id || $leg->destination_node_id !== $node || $leg->status !== 'IN_TRANSIT') {
            return ManifestEligibilityReason::RouteLegNotReady;
        }
        $hasNext = $evidence->legs->contains(fn ($next) => $next->route_plan_id === $leg->route_plan_id && $next->leg_order === $leg->leg_order + 1 && $next->origin_node_id === $node);
        if ($target === 'CI' && ! $hasNext || $target === 'IR' && $hasNext) {
            return ManifestEligibilityReason::RouteLegNotReady;
        }

        return ManifestEligibilityReason::Eligible;
    }

    private function outbound(
        ParcelRecord $p,
        ManifestRecord $m,
        string $node,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        $base = $this->nodeCustody($p, $node, $node);
        if (! $base->eligible()) {
            return $base;
        }
        if ((string) $p->active_route_plan_id !== (string) $m->route_plan_id) {
            return ManifestEligibilityReason::RoutePlanMismatch;
        }
        if ((string) $p->active_route_plan_leg_id !== (string) $m->route_plan_leg_id) {
            return ManifestEligibilityReason::RouteLegMismatch;
        }

        $leg = $evidence->legs->get($m->route_plan_leg_id);
        $ready = $leg !== null && $leg->origin_node_id === $node && in_array($leg->status, ['PENDING', 'ROUTED'], true);

        return $ready ? ManifestEligibilityReason::Eligible : ManifestEligibilityReason::RouteLegNotReady;
    }

    private function departure(
        ParcelRecord $p,
        ManifestRecord $m,
        string $node,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        $base = $this->nodeCustody($p, $node, $node);
        if (! $base->eligible()) {
            return $base;
        }
        $source = $evidence->sourceRows->get($p->parcel_id);
        if ($source === null || (string) $source->destination_node_id !== (string) $m->destination_node_id || (string) $source->route_definition_version_leg_id !== (string) $m->route_definition_version_leg_id) {
            return ManifestEligibilityReason::PreviousMovementMismatch;
        }
        $leg = $evidence->legs->get($p->active_route_plan_leg_id);
        $ready = $leg !== null && $leg->route_plan_id === $p->active_route_plan_id && $leg->origin_node_id === $node
            && $leg->destination_node_id === $m->destination_node_id && $leg->source_route_definition_version_leg_id === $m->route_definition_version_leg_id
            && $leg->status === 'OUTBOUND_CONFIRMED';

        return $ready ? ManifestEligibilityReason::Eligible : ManifestEligibilityReason::RouteLegNotReady;
    }

    private function deliveryAssignment(
        ParcelRecord $p,
        ManifestRecord $m,
        ConsignmentRecord $c,
        string $node,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        $base = $this->nodeCustody($p, $node, $node);
        if (! $base->eligible()) {
            return $base;
        }
        if ((string) $c->delivery_node_id !== $node) {
            return ManifestEligibilityReason::DeliveryNodeMismatch;
        }
        if ($p->active_route_plan_id !== null && $evidence->legs->contains(fn ($leg) => $leg->route_plan_id === $p->active_route_plan_id && $leg->status !== 'RECEIVED')) {
            return ManifestEligibilityReason::DeliveryRouteIncomplete;
        }

        return ManifestEligibilityReason::Eligible;
    }

    private function deliveryCompletion(
        ParcelRecord $p,
        ManifestRecord $m,
        string $node,
        ManifestEligibilityEvidenceDto $evidence,
    ): ManifestEligibilityReason {
        if ((string) $p->current_custody_type !== 'DELIVERY_DRIVER' || (string) $p->current_custodian_id !== (string) $m->assigned_driver_id) {
            return ManifestEligibilityReason::CustodyMismatch;
        }
        $ok = ($task = $evidence->deliveries->get($p->consignment_id)) !== null
            && $task->assigned_driver_id === $m->assigned_driver_id && $task->status === DeliveryTaskStatus::InProgress;

        return $ok ? ManifestEligibilityReason::Eligible : ManifestEligibilityReason::DriverUnavailable;
    }
}
