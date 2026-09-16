<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestTargetApplier
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestRoutePlanner $manifestRoutePlanner,
        private \Modules\Operations\Application\Contracts\ManifestRouteAccess $routeState,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Consignment\Application\Contracts\ManifestConsignmentAccess $consignmentState,
        private \Modules\Operations\Application\DeliveryTaskService $deliveryTasks,
        private \Modules\Operations\Application\Contracts\ManifestTaskAccess $taskState,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function applyTarget(AuthenticatedPrincipal $actor, string $node, object $m, object $p, string $correlationId): array
    {
        $target = (string) $m->manifest_status;
        $route = [
            'route_plan_id' => $m->route_plan_id,
            'route_definition_version_id' => $m->route_definition_version_id,
            'route_plan_leg_id' => $m->route_plan_leg_id,
            'route_definition_version_leg_id' => $m->route_definition_version_leg_id,
        ];
        $activeRoutePlanId = $route['route_plan_id'] ?? $p->active_route_plan_id;
        $activeRouteLegId = $route['route_plan_leg_id'] ?? $p->active_route_plan_leg_id;
        if ($target === 'ROU') {
            $route = $this->manifestRoutePlanner->ensureRoute($actor, $node, $p, $correlationId);
            $activeRoutePlanId = $route['route_plan_id'];
            $activeRouteLegId = $route['route_plan_leg_id'];
        }
        if ($target === 'OS') {
            $leg = $this->routeState->lockDepartureLeg($actor->hqId, $p->active_route_plan_leg_id);
            if ($leg === null) {
                throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'The Route Leg is not ready for departure.');
            }
            $route = [
                'route_plan_id' => (string) $leg->route_plan_id,
                'route_definition_version_id' => (string) $leg->route_definition_version_id,
                'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
                'route_definition_version_leg_id' => (string) $leg->source_route_definition_version_leg_id,
            ];
            $activeRoutePlanId = $route['route_plan_id'];
            $activeRouteLegId = $route['route_plan_leg_id'];
            if ((string) $leg->status === 'OUTBOUND_CONFIRMED') {
                $this->routeState->updateTenantLeg($actor->hqId, $leg->route_plan_leg_id, ['status' => 'IN_TRANSIT', 'updated_at' => $this->clock->now()]);
            }
        }
        if (in_array($target, ['IR', 'CI'], true) && $p->current_status === 'OS') {
            $source = $this->workflow->successfulSourceParcel($actor->hqId, $m->source_manifest_id, $p->parcel_id);
            if ($source === null) {
                throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'The source movement evidence is unavailable.');
            }
            $route = [
                'route_plan_id' => $source->route_plan_id,
                'route_definition_version_id' => $source->route_definition_version_id,
                'route_plan_leg_id' => $source->route_plan_leg_id,
                'route_definition_version_leg_id' => $source->route_definition_version_leg_id,
            ];
            $currentLeg = $this->routeState->lockReceptionLeg($actor->hqId, $source->route_plan_leg_id, $source->route_plan_id);
            if ($currentLeg === null) {
                throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'The source Route Leg is unavailable.');
            }
            $this->routeState->updateTenantLeg($actor->hqId, $source->route_plan_leg_id, ['status' => 'RECEIVED', 'received_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
            $next = $this->routeState->followingLeg($actor->hqId, $source->route_plan_id, $currentLeg->leg_order, $node);
            if ($target === 'CI') {
                if ($next === null) {
                    throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'No following published Route Leg is available.');
                }
                $activeRoutePlanId = $source->route_plan_id;
                $activeRouteLegId = $next->route_plan_leg_id;
            } else {
                $activeRoutePlanId = $source->route_plan_id;
                $activeRouteLegId = null;
                $hasUnreceivedLeg = $this->routeState->hasUnreceivedLeg($actor->hqId, $source->route_plan_id);
                if (!$hasUnreceivedLeg) {
                    $this->routeState->updateTenantPlan($actor->hqId, $source->route_plan_id, ['status' => 'COMPLETED', 'active_slot' => null, 'updated_at' => $this->clock->now()]);
                }
            }
        }
        if ($target === 'OF') {
            $this->routeState->confirmOutboundLeg($actor->hqId, $m->route_plan_leg_id, ['status' => 'OUTBOUND_CONFIRMED', 'updated_at' => $this->clock->now()]);
            $activeRoutePlanId = $m->route_plan_id;
            $activeRouteLegId = $m->route_plan_leg_id;
        }
        $custody = match ($target) {
            'PD', 'PU', 'NPU' => 'PICKUP_DRIVER',
            'OS' => 'LINEHAUL_DRIVER',
            'OD', 'NOK' => 'DELIVERY_DRIVER',
            'OK' => 'RECIPIENT',
            default => 'NODE',
        };
        $custodian = match ($custody) {
            'PICKUP_DRIVER', 'LINEHAUL_DRIVER', 'DELIVERY_DRIVER' => $m->assigned_driver_id,
            'NODE' => $node,
            default => null,
        };
        $nextNode = in_array($custody, ['PICKUP_DRIVER', 'LINEHAUL_DRIVER', 'DELIVERY_DRIVER', 'RECIPIENT'], true) ? null : $node;
        $this->consignmentState->updateTenantParcel($actor->hqId, $p->parcel_id, [
            'current_status' => $target,
            'current_node_id' => $nextNode,
            'current_custody_type' => $custody,
            'current_custodian_id' => $custodian,
            'active_route_plan_id' => $activeRoutePlanId,
            'active_route_plan_leg_id' => $activeRouteLegId,
            'version' => (int) $p->version + 1,
            'updated_at' => $this->clock->now(),
        ]);
        if ($target === 'PD') {
            $this->pickupAssigned($actor, $node, $p, $m);
        }
        if ($target === 'PU') {
            $this->pickupCompleted($p);
        }
        if ($target === 'NPU') {
            $this->pickupFailed($p);
        }
        if ($target === 'IR' && $p->current_status === 'PU') {
            $this->pickupReceived($p);
            if ($this->consignmentState->hasDeliveryNode($actor->hqId, $p->consignment_id, $node)) {
                $this->deliveryTasks->ensurePending($actor, $node, (string) $p->consignment_id);
            }
        }
        if ($target === 'IR' && $p->current_status === 'OS') {
            $this->deliveryTasks->ensurePending($actor, $node, (string) $p->consignment_id);
        }
        if ($target === 'OD') {
            $this->deliveryTasks->ensurePending($actor, $node, (string) $p->consignment_id);
            $this->deliveryTasks->activateFromManifest($actor, $node, (string) $p->consignment_id, (string) $m->assigned_driver_id, (string) $m->manifest_id);
        }
        if ($target === 'OK') {
            $this->deliveryCompleted($actor, $p);
        }
        if ($target === 'NOK') {
            $this->deliveryFailed($actor, $p);
        }
        return [
            'origin_node_id' => $m->origin_node_id ?? $p->current_node_id,
            'destination_node_id' => $m->destination_node_id ?? $nextNode,
            ...$route,
            'assigned_driver_id' => $m->assigned_driver_id,
            'assigned_vehicle_id' => $m->assigned_vehicle_id,
        ];
    }

    public function pickupAssigned(AuthenticatedPrincipal $actor, string $node, object $p, object $m): void
    {
        $task = $this->taskState->lockPickup($actor->hqId, $p->consignment_id);
        if ($task === null) {
            $this->taskState->insertPickup([
                'pickup_task_id' => $this->identifiers->uuid(),
                'hq_id' => $actor->hqId,
                'consignment_id' => $p->consignment_id,
                'node_id' => $node,
                'assigned_driver_id' => $m->assigned_driver_id,
                'status' => 'ASSIGNED',
                'version' => 1,
                'assigned_at' => $this->clock->now(),
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        } else {
            $this->taskState->updatePickup($task->pickup_task_id, [
                'assigned_driver_id' => $m->assigned_driver_id,
                'status' => 'ASSIGNED',
                'version' => (int) $task->version + 1,
                'assigned_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        }
        $this->consignmentState->updatePickupDriver($p->consignment_id, ['pickup_man_id' => $m->assigned_driver_id]);
        $this->taskState->assignDriverMission($m->assigned_driver_id, ['availability_status' => 'ON_MISSION', 'updated_at' => $this->clock->now()]);
    }

    public function pickupCompleted(object $p): void
    {
        $this->taskState->completePickup($p->consignment_id, ['status' => 'COMPLETED', 'completed_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
    }

    public function pickupFailed(object $p): void
    {
        $this->taskState->failPickup($p->consignment_id, ['status' => 'FAILED', 'failed_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
    }

    public function pickupReceived(object $p): void
    {
        $this->taskState->releaseDriverMission($p->current_custodian_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
    }

    public function deliveryCompleted(AuthenticatedPrincipal $actor, object $p): void
    {
        $task = $this->taskState->lockDelivery($actor->hqId, $p->consignment_id);
        if ($task) {
            $this->taskState->updateDelivery($task->delivery_task_id, [
                'status' => 'COMPLETED',
                'delivered_at' => $this->clock->now(),
                'version' => (int) $task->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->taskState->releaseDriverMission($task->assigned_driver_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
        }
    }

    public function deliveryFailed(AuthenticatedPrincipal $actor, object $p): void
    {
        $task = $this->taskState->lockDelivery($actor->hqId, $p->consignment_id);
        if ($task) {
            $this->taskState->updateDelivery($task->delivery_task_id, ['status' => 'FAILED', 'version' => (int) $task->version + 1, 'updated_at' => $this->clock->now()]);
            $this->taskState->releaseDriverMission($task->assigned_driver_id, ['availability_status' => 'AVAILABLE', 'updated_at' => $this->clock->now()]);
        }
    }
}
