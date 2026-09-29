<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanResolutionEvidenceRecord;

final class EloquentManifestRouteAccess implements ManifestRouteAccessInterface
{
    public function hasUnreceivedLeg(?string $hqId, ?string $activeRoutePlanId): bool
    {
        return RoutePlanLegRecord::query()
            ->where(['hq_id' => $hqId, 'route_plan_id' => $activeRoutePlanId])
            ->whereNot('status', 'RECEIVED')
            ->exists();
    }

    public function plansByIds(array $referenceIds, string $hq): Collection
    {
        return RoutePlanRecord::query()->where('hq_id', $hq)->whereIn('route_plan_id', $referenceIds)
            ->whereHas('consignment', fn ($consignment) => $consignment->where('hq_id', $hq))
            ->whereHas('definition', fn ($definition) => $definition->where('hq_id', $hq))
            ->with(['consignment', 'definition'])->get();
    }

    public function legsByIds(array $referenceIds, string $hq): Collection
    {
        return RoutePlanLegRecord::query()->where('hq_id', $hq)->whereIn('route_plan_leg_id', $referenceIds)->get();
    }

    public function outboundLegContexts(string $hq, string $node): Collection
    {
        return RoutePlanLegRecord::query()->where(['hq_id' => $hq, 'origin_node_id' => $node])
            ->whereIn('status', ['PENDING', 'ROUTED'])->whereHas('plan.consignment')
            ->whereHas('activeParcels', fn ($parcels) => $parcels
                ->whereColumn('active_route_plan_id', 'route_plan_legs.route_plan_id')->whereIn('current_status', ['ROU', 'CI']))
            ->with('plan.consignment')->get()->sortBy('plan.consignment.consignment_number')->values();
    }

    public function planSummary(string $hq, ?string $id): ?RoutePlanRecord
    {
        return RoutePlanRecord::query()->where(['hq_id' => $hq, 'route_plan_id' => $id])
            ->whereHas('consignment')->whereHas('definition')->with(['consignment', 'definition'])->first();
    }

    public function leg(string $hq, ?string $id): ?RoutePlanLegRecord
    {
        return RoutePlanLegRecord::query()
            ->where(['hq_id' => $hq, 'route_plan_leg_id' => $id])
            ->first();
    }

    public function lockDepartureLeg(?string $hqId, ?string $activeRoutePlanLegId): ?RoutePlanLegRecord
    {
        return RoutePlanLegRecord::query()->where(['hq_id' => $hqId, 'route_plan_leg_id' => $activeRoutePlanLegId])
            ->whereHas('plan', fn ($plan) => $plan->where('hq_id', $hqId))
            ->whereIn('status', ['OUTBOUND_CONFIRMED', 'IN_TRANSIT'])->lockForUpdate()
            ->with(['plan' => fn ($plan) => $plan->lockForUpdate()])->first();
    }

    public function updateTenantLeg(
        ?string $hqId,
        ?string $routePlanLegId,
        array $changes,
    ): void {
        RoutePlanLegRecord::query()
            ->where(['hq_id' => $hqId, 'route_plan_leg_id' => $routePlanLegId])
            ->update($changes);
    }

    public function lockReceptionLeg(
        ?string $hqId,
        ?string $routePlanLegId,
        ?string $routePlanId,
    ): ?RoutePlanLegRecord {
        return RoutePlanLegRecord::query()
            ->where([
                'hq_id' => $hqId,
                'route_plan_leg_id' => $routePlanLegId,
                'route_plan_id' => $routePlanId,
            ])
            ->lockForUpdate()
            ->first();
    }

    public function followingLeg(
        ?string $hqId,
        ?string $routePlanId,
        int $legOrder,
        string $node,
    ): ?RoutePlanLegRecord {
        return RoutePlanLegRecord::query()
            ->where([
                'hq_id' => $hqId,
                'route_plan_id' => $routePlanId,
                'leg_order' => (int) $legOrder + 1,
                'origin_node_id' => $node,
            ])
            ->first();
    }

    public function updateTenantPlan(
        ?string $hqId,
        ?string $routePlanId,
        array $changes,
    ): void {
        RoutePlanRecord::query()
            ->where(['hq_id' => $hqId, 'route_plan_id' => $routePlanId])
            ->update($changes);
    }

    public function confirmOutboundLeg(
        ?string $hqId,
        ?string $routePlanLegId,
        array $changes,
    ): void {
        // One atomic update preserves an existing routing timestamp under concurrent confirmations.
        $changes['routed_at'] = DB::raw('COALESCE(routed_at, CURRENT_TIMESTAMP(6))');
        RoutePlanLegRecord::query()
            ->where(['hq_id' => $hqId, 'route_plan_leg_id' => $routePlanLegId])
            ->whereIn('status', ['PENDING', 'ROUTED'])
            ->update($changes);
    }

    public function lockActivePlan(?string $hqId, ?string $consignmentId): ?RoutePlanRecord
    {
        return RoutePlanRecord::query()
            ->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->whereIn('status', ['PLANNED', 'IN_PROGRESS'])
            ->lockForUpdate()
            ->first();
    }

    public function lockNextOriginLeg(
        ?string $hqId,
        ?string $routePlanId,
        string $node,
    ): ?RoutePlanLegRecord {
        return RoutePlanLegRecord::query()
            ->where([
                'hq_id' => $hqId,
                'route_plan_id' => $routePlanId,
                'origin_node_id' => $node,
            ])
            ->whereIn('status', ['PENDING', 'ROUTED'])
            ->orderBy('leg_order')
            ->lockForUpdate()
            ->first();
    }

    public function previousLegReceived(?string $routePlanId, int $legOrder): bool
    {
        return RoutePlanLegRecord::query()
            ->where([
                'route_plan_id' => $routePlanId,
                'leg_order' => (int) $legOrder - 1,
                'status' => 'RECEIVED',
            ])
            ->exists();
    }

    public function updateLeg(?string $routePlanLegId, array $changes): void
    {
        RoutePlanLegRecord::query()
            ->where('route_plan_leg_id', $routePlanLegId)
            ->update($changes);
    }

    public function updatePlan(?string $routePlanId, array $changes): void
    {
        RoutePlanRecord::query()
            ->where('route_plan_id', $routePlanId)
            ->update($changes);
    }

    public function insertPlan(array $attributes): RoutePlanRecord
    {
        return RoutePlanRecord::query()->forceCreate($attributes);
    }

    public function legacyLegsByOrder(
        ?string $hqId,
        ?string $routeDefinitionId,
    ): SupportCollection {
        return RouteDefinitionLegRecord::query()->where(['hq_id' => $hqId, 'route_definition_id' => $routeDefinitionId, 'status' => 'ACTIVE'])
            ->pluck('route_definition_leg_id', 'leg_order');
    }

    public function insertPlanLegs(array $attributes): void
    {
        RoutePlanLegRecord::query()->insert($attributes);
    }

    public function insertResolutionEvidence(array $attributes): void
    {
        (new RoutePlanResolutionEvidenceRecord)->forceFill($attributes)->save();
    }

    public function legsForPlansAndLeg(?string $hqId, array $routePlanIds, ?string $routePlanLegId): Collection
    {
        return RoutePlanLegRecord::query()->where('hq_id', $hqId)
            ->where(fn ($query) => $query->whereIn('route_plan_id', $routePlanIds)->orWhere('route_plan_leg_id', $routePlanLegId))
            ->get()->keyBy('route_plan_leg_id');
    }
}
