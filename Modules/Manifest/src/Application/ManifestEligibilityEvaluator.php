<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Modules\Manifest\Domain\ManifestEligibilityReason as Reason;
use Modules\Manifest\Domain\ManifestPolicy;

final readonly class ManifestEligibilityEvaluator
{
    public function __construct(
        private \Modules\Consignment\Application\Contracts\ManifestConsignmentAccess $consignmentState,
        private \Modules\Operations\Application\Contracts\ManifestTaskAccess $taskState,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Operations\Application\Contracts\ManifestRouteAccess $routeState,
        private ManifestPolicy $policy,
    )
    {
    }
    /** @return array<string,mixed> */

    public function evaluate(object $parcel, object $manifest, string $nodeId): array
    {
        $consignment = $this->consignmentState->consignment($manifest->hq_id, $parcel->consignment_id);
        if ($consignment === null) {
            return Reason::metadata(Reason::ParcelNotFound);
        }
        if ($consignment->service_offering_id !== null && $consignment->commercial_pricing_state !== 'LOCKED') {
            return Reason::metadata(Reason::PricingStale);
        }
        if (!$this->policy->canTransition((string) $parcel->current_status, (string) $manifest->manifest_status)) {
            return Reason::metadata(Reason::StatusNotAllowed);
        }
        return match ((string) $manifest->manifest_status) {
            'PD' => $this->nodeCustody($parcel, $consignment->pickup_node_id, $nodeId),
            'PU', 'NPU' => $this->pickupAssignment($parcel, $manifest, $nodeId),
            'IR' => (string) $parcel->current_status === 'OS' ? $this->movementReception($parcel, $manifest, $nodeId, 'IR') : $this->pickupReception($parcel, $manifest, $nodeId),
            'ROU' => $this->nodeCustody($parcel, $nodeId, $nodeId),
            'OF' => $this->outbound($parcel, $manifest, $nodeId),
            'OS' => $this->departure($parcel, $manifest, $nodeId),
            'CI' => $this->movementReception($parcel, $manifest, $nodeId, 'CI'),
            'OD' => $this->deliveryAssignment($parcel, $manifest, $consignment, $nodeId),
            'OK', 'NOK' => $this->deliveryCompletion($parcel, $manifest, $nodeId),
            default => Reason::metadata(Reason::StatusNotAllowed),
        };
    }

    public function candidateScope(object $manifest, string $nodeId): array
    {
        $target = (string) $manifest->manifest_status;
        $movementReception = in_array($target, ['IR', 'CI'], true) && (string) $manifest->operational_context_type !== 'PICKUP_RECEPTION';
        $custody = $movementReception ? 'LINEHAUL_DRIVER' : (in_array($target, ['PU', 'NPU'], true) ? 'PICKUP_DRIVER' : (in_array($target, ['OK', 'NOK'], true) ? 'DELIVERY_DRIVER' : null));
        return [
            'source_statuses' => $this->policy->sourceStatuses($target),
            'requires_source_manifest' => $movementReception || $target === 'OS',
            'source_manifest_id' => $movementReception || $target === 'OS' ? $manifest->source_manifest_id : null,
            'custody_type' => $custody,
            'node_id' => $custody === null ? $nodeId : null,
        ];
    }
    /** @return array<string,mixed> */

    private function nodeCustody(object $p, mixed $requiredNode, string $node): array
    {
        if ((string) $requiredNode !== $node || (string) $p->current_node_id !== $node) {
            return Reason::metadata(Reason::CurrentNodeMismatch);
        }
        if ((string) $p->current_custody_type !== 'NODE' || (string) $p->current_custodian_id !== $node) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        return Reason::metadata(Reason::Eligible);
    }
    /** @return array<string,mixed> */

    private function pickupAssignment(object $p, object $m, string $node): array
    {
        if ((string) $p->current_custody_type !== 'PICKUP_DRIVER' || (string) $p->current_custodian_id !== (string) $m->assigned_driver_id) {
            return Reason::metadata(Reason::PickupAssignmentMismatch);
        }
        $ok = $this->taskState->pickupAssignmentExists($m->hq_id, $p->consignment_id, $m->assigned_driver_id, $node);
        return Reason::metadata($ok ? Reason::Eligible : Reason::PickupAssignmentMismatch);
    }
    /** @return array<string,mixed> */

    private function pickupReception(object $p, object $m, string $node): array
    {
        if ((string) $p->current_custody_type !== 'PICKUP_DRIVER' || $p->current_custodian_id === null) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        $ok = $this->taskState->completedPickupExists($m->hq_id, $p->consignment_id, $p->current_custodian_id, $node);
        return Reason::metadata($ok ? Reason::Eligible : Reason::PickupTaskNotCompleted);
    }
    /** @return array<string,mixed> */

    private function movementReception(object $p, object $m, string $node, string $target): array
    {
        if ((string) $p->current_custody_type !== 'LINEHAUL_DRIVER' || (string) $p->current_custodian_id !== (string) $m->assigned_driver_id) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        $e = $this->workflow->successfulSourceParcel($m->hq_id, $m->source_manifest_id, $p->parcel_id);
        if ($e === null || (string) $e->destination_node_id !== $node || (string) $e->assigned_driver_id !== (string) $m->assigned_driver_id || (string) $e->assigned_vehicle_id !== (string) $m->assigned_vehicle_id) {
            return Reason::metadata(Reason::PreviousMovementMismatch);
        }
        $leg = $this->routeState->inTransitLeg($m->hq_id, $e->route_plan_leg_id, $e->route_plan_id, $node);
        if ($leg === null) {
            return Reason::metadata(Reason::RouteLegNotReady);
        }
        $hasNext = $this->routeState->hasFollowingLeg($m->hq_id, $leg->route_plan_id, $leg->leg_order, $node);
        if ($target === 'CI' && !$hasNext || $target === 'IR' && $hasNext) {
            return Reason::metadata(Reason::RouteLegNotReady);
        }
        return Reason::metadata(Reason::Eligible);
    }
    /** @return array<string,mixed> */

    private function outbound(object $p, object $m, string $node): array
    {
        $base = $this->nodeCustody($p, $node, $node);
        if (!$base['eligible']) {
            return $base;
        }
        if ((string) $p->active_route_plan_id !== (string) $m->route_plan_id) {
            return Reason::metadata(Reason::RoutePlanMismatch);
        }
        if ((string) $p->active_route_plan_leg_id !== (string) $m->route_plan_leg_id) {
            return Reason::metadata(Reason::RouteLegMismatch);
        }
        return Reason::metadata($this->routeState->outboundLegReady($m->hq_id, $m->route_plan_leg_id, $node) ? Reason::Eligible : Reason::RouteLegNotReady);
    }
    /** @return array<string,mixed> */

    private function departure(object $p, object $m, string $node): array
    {
        $base = $this->nodeCustody($p, $node, $node);
        if (!$base['eligible']) {
            return $base;
        }
        $source = $this->workflow->successfulSourceParcel($m->hq_id, $m->source_manifest_id, $p->parcel_id);
        if ($source === null || (string) $source->destination_node_id !== (string) $m->destination_node_id || (string) $source->route_definition_version_leg_id !== (string) $m->route_definition_version_leg_id) {
            return Reason::metadata(Reason::PreviousMovementMismatch);
        }
        $leg = $this->routeState->departureLeg($m->hq_id, $p->active_route_plan_leg_id, $p->active_route_plan_id, $m->destination_node_id, $m->route_definition_version_leg_id, $node);
        return Reason::metadata($leg ? Reason::Eligible : Reason::RouteLegNotReady);
    }
    /** @return array<string,mixed> */

    private function deliveryAssignment(object $p, object $m, object $c, string $node): array
    {
        $base = $this->nodeCustody($p, $node, $node);
        if (!$base['eligible']) {
            return $base;
        }
        if ((string) $c->delivery_node_id !== $node) {
            return Reason::metadata(Reason::DeliveryNodeMismatch);
        }
        if ($p->active_route_plan_id !== null && $this->routeState->hasUnreceivedLeg($m->hq_id, $p->active_route_plan_id)) {
            return Reason::metadata(Reason::DeliveryRouteIncomplete);
        }
        return Reason::metadata(Reason::Eligible);
    }
    /** @return array<string,mixed> */

    private function deliveryCompletion(object $p, object $m, string $node): array
    {
        if ((string) $p->current_custody_type !== 'DELIVERY_DRIVER' || (string) $p->current_custodian_id !== (string) $m->assigned_driver_id) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        $ok = $this->taskState->deliveryInProgress($m->hq_id, $p->consignment_id, $m->assigned_driver_id, $node);
        return Reason::metadata($ok ? Reason::Eligible : Reason::DriverUnavailable);
    }
}
