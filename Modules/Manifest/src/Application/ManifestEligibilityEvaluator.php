<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Manifest\Domain\ManifestEligibilityReason as Reason;
use Modules\Manifest\Domain\ManifestPolicy;

final readonly class ManifestEligibilityEvaluator
{
    public function __construct(private ManifestPolicy $policy) {}

    /** @return array<string, mixed> */
    public function evaluate(object $parcel, object $manifest, string $nodeId): array
    {
        $consignment = DB::table('consignments')->where([
            'hq_id' => $manifest->hq_id,
            'consignment_id' => $parcel->consignment_id,
        ])->first();
        if ($consignment === null) {
            return Reason::metadata(Reason::ParcelNotFound);
        }
        if ($consignment->service_offering_id !== null && $consignment->commercial_pricing_state !== 'LOCKED') {
            return Reason::metadata(Reason::PricingStale);
        }
        if (! $this->policy->canTransition((string) $parcel->current_status, (string) $manifest->manifest_status)) {
            return Reason::metadata(Reason::StatusNotAllowed);
        }

        return match ((string) $manifest->operational_context_type) {
            'PICKUP_RECEPTION' => $this->pickupReception($parcel, $manifest, $nodeId),
            'TRANSPORT_RECEPTION' => $this->transportReception($parcel, $manifest, $nodeId),
            'ROUTE_OUTBOUND' => $this->routeOutbound($parcel, $manifest, $nodeId),
            'DELIVERY_ASSIGNMENT' => $this->deliveryAssignment($parcel, $manifest, $consignment, $nodeId),
            default => Reason::metadata(Reason::StatusNotAllowed),
        };
    }

    public function applyCandidateScope(Builder $query, object $manifest, string $nodeId): void
    {
        $query->whereIn('p.current_status', $this->policy->sourceStatuses((string) $manifest->manifest_status));

        match ((string) $manifest->operational_context_type) {
            'PICKUP_RECEPTION' => $query
                ->where('p.current_custody_type', 'PICKUP_DRIVER')
                ->whereExists(fn (Builder $task) => $task->selectRaw('1')->from('pickup_tasks as pt')
                    ->whereColumn('pt.consignment_id', 'p.consignment_id')
                    ->whereColumn('pt.assigned_driver_id', 'p.current_custodian_id')
                    ->where(['pt.hq_id' => $manifest->hq_id, 'pt.node_id' => $nodeId, 'pt.status' => 'COMPLETED'])),
            'TRANSPORT_RECEPTION' => $query
                ->where('p.current_custody_type', 'TRANSPORT_RUN')
                ->where('p.active_transport_run_id', $manifest->transport_run_id),
            'ROUTE_OUTBOUND' => $query
                ->where('p.current_node_id', $nodeId)
                ->where('p.active_route_plan_id', $manifest->route_plan_id)
                ->where('p.active_route_plan_leg_id', $manifest->route_plan_leg_id),
            'DELIVERY_ASSIGNMENT' => $query->where('p.current_node_id', $nodeId),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** @return array<string, mixed> */
    private function pickupReception(object $parcel, object $manifest, string $nodeId): array
    {
        if ((string) $parcel->current_custody_type !== 'PICKUP_DRIVER' || $parcel->current_custodian_id === null) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        $completed = DB::table('pickup_tasks')->where([
            'hq_id' => $manifest->hq_id,
            'consignment_id' => $parcel->consignment_id,
            'node_id' => $nodeId,
            'assigned_driver_id' => $parcel->current_custodian_id,
            'status' => 'COMPLETED',
        ])->exists();

        return Reason::metadata($completed ? Reason::Eligible : Reason::PickupTaskNotCompleted);
    }

    /** @return array<string, mixed> */
    private function transportReception(object $parcel, object $manifest, string $nodeId): array
    {
        if ((string) $parcel->current_custody_type !== 'TRANSPORT_RUN'
            || (string) $parcel->current_custodian_id !== (string) $manifest->transport_run_id) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        if ((string) $parcel->active_transport_run_id !== (string) $manifest->transport_run_id
            || ! DB::table('transport_run_parcels')->where([
                'hq_id' => $manifest->hq_id,
                'transport_run_id' => $manifest->transport_run_id,
                'parcel_id' => $parcel->parcel_id,
            ])->exists()) {
            return Reason::metadata(Reason::TransportRunMismatch);
        }
        if ((string) $parcel->active_route_plan_id !== (string) $manifest->route_plan_id) {
            return Reason::metadata(Reason::RoutePlanMismatch);
        }
        if ((string) $parcel->active_route_plan_leg_id !== (string) $manifest->route_plan_leg_id) {
            return Reason::metadata(Reason::RouteLegMismatch);
        }
        $runArrived = DB::table('transport_runs')->where([
            'hq_id' => $manifest->hq_id,
            'transport_run_id' => $manifest->transport_run_id,
            'route_plan_leg_id' => $manifest->route_plan_leg_id,
            'destination_node_id' => $nodeId,
            'status' => 'ARRIVED',
        ])->exists();
        if (! $runArrived) {
            return Reason::metadata(Reason::TransportRunNotArrived);
        }
        $legArrived = DB::table('route_plan_legs')->where([
            'hq_id' => $manifest->hq_id,
            'route_plan_leg_id' => $manifest->route_plan_leg_id,
            'route_plan_id' => $manifest->route_plan_id,
            'destination_node_id' => $nodeId,
            'status' => 'ARRIVED',
        ])->exists();

        return Reason::metadata($legArrived ? Reason::Eligible : Reason::RouteLegNotReady);
    }

    /** @return array<string, mixed> */
    private function routeOutbound(object $parcel, object $manifest, string $nodeId): array
    {
        if ((string) $parcel->current_node_id !== $nodeId) {
            return Reason::metadata(Reason::CurrentNodeMismatch);
        }
        if ((string) $parcel->current_custody_type !== 'NODE'
            || (string) $parcel->current_custodian_id !== $nodeId) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        if ((string) $parcel->active_route_plan_id !== (string) $manifest->route_plan_id) {
            return Reason::metadata(Reason::RoutePlanMismatch);
        }
        if ((string) $parcel->active_route_plan_leg_id !== (string) $manifest->route_plan_leg_id) {
            return Reason::metadata(Reason::RouteLegMismatch);
        }
        $legReady = DB::table('route_plan_legs')->where([
            'hq_id' => $manifest->hq_id,
            'route_plan_leg_id' => $manifest->route_plan_leg_id,
            'route_plan_id' => $manifest->route_plan_id,
            'origin_node_id' => $nodeId,
            'status' => 'ROUTED',
        ])->exists();
        if (! $legReady) {
            return Reason::metadata(Reason::RouteLegNotReady);
        }
        $plan = DB::table('route_plans')->where([
            'hq_id' => $manifest->hq_id,
            'route_plan_id' => $manifest->route_plan_id,
            'status' => 'IN_PROGRESS',
        ])->first();
        if ($plan === null) {
            return Reason::metadata(Reason::RoutePlanMismatch);
        }
        if ($plan->route_definition_version_id === null || ! DB::table('route_definition_versions')->where([
            'hq_id' => $manifest->hq_id,
            'route_definition_version_id' => $plan->route_definition_version_id,
        ])->whereIn('status', ['PUBLISHED', 'SUPERSEDED'])->exists()) {
            return Reason::metadata(Reason::ConfigVersionUnavailable);
        }

        return Reason::metadata(Reason::Eligible);
    }

    /** @return array<string, mixed> */
    private function deliveryAssignment(object $parcel, object $manifest, object $consignment, string $nodeId): array
    {
        if ((string) $parcel->current_node_id !== $nodeId) {
            return Reason::metadata(Reason::CurrentNodeMismatch);
        }
        if ((string) $parcel->current_custody_type !== 'NODE'
            || (string) $parcel->current_custodian_id !== $nodeId) {
            return Reason::metadata(Reason::CustodyMismatch);
        }
        if ((string) $consignment->delivery_node_id !== $nodeId) {
            return Reason::metadata(Reason::DeliveryNodeMismatch);
        }
        if ($parcel->active_route_plan_id !== null && DB::table('route_plan_legs')->where([
            'hq_id' => $manifest->hq_id,
            'route_plan_id' => $parcel->active_route_plan_id,
        ])->whereNot('status', 'RECEIVED')->exists()) {
            return Reason::metadata(Reason::DeliveryRouteIncomplete);
        }
        if (! $this->driverAvailable($manifest, $nodeId)) {
            return Reason::metadata(Reason::DriverUnavailable);
        }
        if ($manifest->assigned_vehicle_id !== null && ! DB::table('vehicles')->where([
            'hq_id' => $manifest->hq_id,
            'vehicle_id' => $manifest->assigned_vehicle_id,
            'home_node_id' => $nodeId,
            'status' => 'ACTIVE',
            'availability_status' => 'AVAILABLE',
        ])->exists()) {
            return Reason::metadata(Reason::VehicleUnavailable);
        }

        return Reason::metadata(Reason::Eligible);
    }

    private function driverAvailable(object $manifest, string $nodeId): bool
    {
        if ($manifest->assigned_driver_id === null) {
            return false;
        }

        return DB::table('drivers as d')->where([
            'd.hq_id' => $manifest->hq_id,
            'd.driver_id' => $manifest->assigned_driver_id,
            'd.home_node_id' => $nodeId,
            'd.status' => 'ACTIVE',
            'd.availability_status' => 'AVAILABLE',
        ])->whereExists(fn (Builder $capability) => $capability->selectRaw('1')
            ->from('driver_capabilities as dc')
            ->whereColumn('dc.driver_id', 'd.driver_id')
            ->whereColumn('dc.hq_id', 'd.hq_id')
            ->where('dc.capability', 'DELIVERY'))
            ->exists();
    }
}
