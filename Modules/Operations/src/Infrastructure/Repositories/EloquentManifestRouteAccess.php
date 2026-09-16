<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Modules\Operations\Application\Contracts\ManifestRouteAccess;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanResolutionEvidenceRecord;

final class EloquentManifestRouteAccess implements ManifestRouteAccess
{
    public function inTransitLeg(?string $hqId, ?string $routePlanLegId, ?string $routePlanId, string $node): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'route_plan_leg_id' => $routePlanLegId,
            'route_plan_id' => $routePlanId,
            'destination_node_id' => $node,
            'status' => 'IN_TRANSIT',
        ])->first();
    }

    public function hasFollowingLeg(?string $hqId, ?string $routePlanId, int $legOrder, string $node): bool
    {
        return RoutePlanLegRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'route_plan_id' => $routePlanId,
            'leg_order' => (int) $legOrder + 1,
            'origin_node_id' => $node,
        ])->exists();
    }

    public function outboundLegReady(?string $hqId, ?string $routePlanLegId, string $node): bool
    {
        return RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_leg_id' => $routePlanLegId, 'origin_node_id' => $node])->whereIn('status', ['PENDING', 'ROUTED'])->exists();
    }

    public function departureLeg(
        ?string $hqId,
        ?string $activeRoutePlanLegId,
        ?string $activeRoutePlanId,
        ?string $destinationNodeId,
        ?string $routeDefinitionVersionLegId,
        string $node,
    ): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'route_plan_leg_id' => $activeRoutePlanLegId,
            'route_plan_id' => $activeRoutePlanId,
            'origin_node_id' => $node,
            'destination_node_id' => $destinationNodeId,
            'source_route_definition_version_leg_id' => $routeDefinitionVersionLegId,
            'status' => 'OUTBOUND_CONFIRMED',
        ])->first();
    }

    public function hasUnreceivedLeg(?string $hqId, ?string $activeRoutePlanId): bool
    {
        return RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_id' => $activeRoutePlanId])->whereNot('status', 'RECEIVED')->exists();
    }

    public function plansByIds(array $referenceIds, string $hq): array
    {
        return RoutePlanRecord::query()->toBase()->from('route_plans as p')->join('consignments as c', fn($j) => $j->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id'))->join('route_definitions as d', fn($j) => $j->on('d.route_definition_id', '=', 'p.route_definition_id')->on('d.hq_id', '=', 'p.hq_id'))->where('p.hq_id', $hq)->whereIn('p.route_plan_id', $referenceIds)->get(['p.*', 'c.consignment_number', 'd.route_code', 'd.route_title'])->all();
    }

    public function legsByIds(array $referenceIds, string $hq): array
    {
        return RoutePlanLegRecord::query()->toBase()->where('hq_id', $hq)->whereIn('route_plan_leg_id', $referenceIds)->get()->all();
    }

    public function outboundLegContexts(string $hq, string $node): array
    {
        return RoutePlanLegRecord::query()->toBase()->from('route_plan_legs as l')->join('route_plans as p', 'p.route_plan_id', '=', 'l.route_plan_id')->join('consignments as c', 'c.consignment_id', '=', 'p.consignment_id')->where(['l.hq_id' => $hq, 'l.origin_node_id' => $node])->whereIn('l.status', ['PENDING', 'ROUTED'])->whereExists(fn(Builder $q) => $q->selectRaw('1')->from('parcels as parcel')->whereColumn('parcel.active_route_plan_leg_id', 'l.route_plan_leg_id')->whereColumn('parcel.active_route_plan_id', 'l.route_plan_id')->whereIn('parcel.current_status', ['ROU', 'CI']))->orderBy('c.consignment_number')->get(['l.*', 'p.route_definition_version_id', 'c.consignment_number'])->all();
    }

    public function planSummary(string $hq, ?string $id): ?object
    {
        return RoutePlanRecord::query()->toBase()->from('route_plans as p')->join('consignments as c', 'c.consignment_id', '=', 'p.consignment_id')->join('route_definitions as d', 'd.route_definition_id', '=', 'p.route_definition_id')->where(['p.hq_id' => $hq, 'p.route_plan_id' => $id])->first();
    }

    public function leg(string $hq, ?string $id): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hq, 'route_plan_leg_id' => $id])->first();
    }

    public function lockDepartureLeg(?string $hqId, ?string $activeRoutePlanLegId): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->from('route_plan_legs as l')->join('route_plans as plan', function ($join): void {
            $join->on('plan.route_plan_id', '=', 'l.route_plan_id')->on('plan.hq_id', '=', 'l.hq_id');
        })->where(['l.hq_id' => $hqId, 'l.route_plan_leg_id' => $activeRoutePlanLegId])->whereIn('l.status', ['OUTBOUND_CONFIRMED', 'IN_TRANSIT'])->lockForUpdate()->first(['l.*', 'plan.route_definition_version_id']);
    }

    public function updateTenantLeg(?string $hqId, ?string $routePlanLegId, array $changes): void
    {
        RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_leg_id' => $routePlanLegId])->update($changes);
    }

    public function lockReceptionLeg(?string $hqId, ?string $routePlanLegId, ?string $routePlanId): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_leg_id' => $routePlanLegId, 'route_plan_id' => $routePlanId])->lockForUpdate()->first();
    }

    public function followingLeg(?string $hqId, ?string $routePlanId, int $legOrder, string $node): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'route_plan_id' => $routePlanId,
            'leg_order' => (int) $legOrder + 1,
            'origin_node_id' => $node,
        ])->first();
    }

    public function updateTenantPlan(?string $hqId, ?string $routePlanId, array $changes): void
    {
        RoutePlanRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_id' => $routePlanId])->update($changes);
    }

    public function confirmOutboundLeg(?string $hqId, ?string $routePlanLegId, array $changes): void
    {
        $changes['routed_at'] = DB::raw('COALESCE(routed_at, CURRENT_TIMESTAMP(6))');
        RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_leg_id' => $routePlanLegId])->whereIn('status', ['PENDING', 'ROUTED'])->update($changes);
    }

    public function lockActivePlan(?string $hqId, ?string $consignmentId): ?object
    {
        return RoutePlanRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->lockForUpdate()->first();
    }

    public function lockNextOriginLeg(?string $hqId, ?string $routePlanId, string $node): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_id' => $routePlanId, 'origin_node_id' => $node])->whereIn('status', ['PENDING', 'ROUTED'])->orderBy('leg_order')->lockForUpdate()->first();
    }

    public function previousLegReceived(?string $routePlanId, int $legOrder): bool
    {
        return RoutePlanLegRecord::query()->toBase()->where(['route_plan_id' => $routePlanId, 'leg_order' => (int) $legOrder - 1, 'status' => 'RECEIVED'])->exists();
    }

    public function updateLeg(?string $routePlanLegId, array $changes): void
    {
        RoutePlanLegRecord::query()->toBase()->where('route_plan_leg_id', $routePlanLegId)->update($changes);
    }

    public function updatePlan(?string $routePlanId, array $changes): void
    {
        RoutePlanRecord::query()->toBase()->where('route_plan_id', $routePlanId)->update($changes);
    }

    public function sourceLegs(?string $hqId, ?string $routeDefinitionVersionId): array
    {
        return RouteDefinitionVersionLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_definition_version_id' => $routeDefinitionVersionId])->orderBy('leg_order')->get()->all();
    }

    public function insertPlan(array $attributes): void
    {
        RoutePlanRecord::query()->toBase()->insert($attributes);
    }

    public function legacyLegId(?string $hqId, ?string $routeDefinitionId, int $legOrder): ?string
    {
        return RouteDefinitionLegRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'route_definition_id' => $routeDefinitionId,
            'leg_order' => $legOrder,
            'status' => 'ACTIVE',
        ])->value('route_definition_leg_id');
    }

    public function insertPlanLeg(array $attributes): void
    {
        RoutePlanLegRecord::query()->toBase()->insert($attributes);
    }

    public function insertResolutionEvidence(array $attributes): void
    {
        RoutePlanResolutionEvidenceRecord::query()->toBase()->insert($attributes);
    }

    public function plan(?string $id): ?object
    {
        return RoutePlanRecord::query()->toBase()->where('route_plan_id', $id)->first();
    }
}
