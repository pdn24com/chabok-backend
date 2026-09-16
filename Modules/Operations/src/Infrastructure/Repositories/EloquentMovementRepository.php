<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Operations\Application\Repositories\MovementRepository;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanResolutionEvidenceRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

final class EloquentMovementRepository implements MovementRepository
{
    public function activePlan(string $hqId, string $consignmentId): ?object
    {
        return RoutePlanRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->first();
    }

    public function sourceLegs(string $hqId, string $versionId): array
    {
        return RouteDefinitionVersionLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_definition_version_id' => $versionId])->orderBy('leg_order')->get()->all();
    }

    public function legacyLegId(string $hqId, int $legOrder, string $definitionId): ?string
    {
        return RouteDefinitionLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId, 'leg_order' => $legOrder, 'status' => 'ACTIVE'])->value('route_definition_leg_id');
    }

    public function lockActivePlan(string $hqId, string $consignmentId): ?object
    {
        return RoutePlanRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->lockForUpdate()->first();
    }

    public function lockPendingLeg(string $hqId, string $planId, string $nodeId): ?object
    {
        return RoutePlanLegRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_id' => $planId, 'origin_node_id' => $nodeId, 'status' => 'PENDING'])->orderBy('leg_order')->lockForUpdate()->first();
    }

    public function previousLegReceived(string $planId, int $previousOrder): bool
    {
        return RoutePlanLegRecord::query()->toBase()->where(['route_plan_id' => $planId, 'leg_order' => $previousOrder, 'status' => 'RECEIVED'])->exists();
    }

    public function activePlanId(string $hqId, string $consignmentId): ?string
    {
        return RoutePlanRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->value('route_plan_id');
    }

    public function plan(string $hqId, string $id): ?object
    {
        return RoutePlanRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_id' => $id])->first();
    }

    public function plansVisibleAtNode(string $hqId, string $nodeId): array
    {
        return RoutePlanRecord::query()->toBase()->from('route_plans as p')->join('consignments as c', function ($join): void {
            $join->on('c.hq_id', '=', 'p.hq_id')->on('c.consignment_id', '=', 'p.consignment_id');
        })->where('p.hq_id', $hqId)->where(function ($query) use ($nodeId): void {
            $query->where('c.pickup_node_id', $nodeId)->orWhereExists(fn($legs) => $legs->selectRaw('1')->from('route_plan_legs as visible_leg')->whereColumn('visible_leg.route_plan_id', 'p.route_plan_id')->where(fn($nodes) => $nodes->where('visible_leg.origin_node_id', $nodeId)->orWhere('visible_leg.destination_node_id', $nodeId)));
        })->orderByDesc('p.created_at')->get(['p.*'])->all();
    }

    public function provinceForActiveCity(string $cityId): ?string
    {
        return DB::table('cities')->where(['city_id' => $cityId, 'is_active' => true])->value('province_id');
    }

    public function configurationEvidence(string $hqId, string $planId, ?string $versionId): ?object
    {
        return RoutePlanResolutionEvidenceRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_plan_id' => $planId, 'route_definition_version_id' => $versionId])->first();
    }

    public function configurationVersion(string $hqId, string $definitionId, ?string $versionId): ?object
    {
        return RouteDefinitionVersionRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId, 'route_definition_version_id' => $versionId])->first();
    }

    public function coverageVersionStatus(string $hqId, string $versionId): ?string
    {
        return CoveragePolicyVersionRecord::query()->toBase()->where(['hq_id' => $hqId, 'coverage_policy_version_id' => $versionId])->value('status');
    }

    public function sourceLegsAvailable(string $planId): bool
    {
        return RoutePlanLegRecord::query()->toBase()->from('route_plan_legs as plan_leg')->where('plan_leg.route_plan_id', $planId)->whereNotExists(fn($query) => $query->selectRaw('1')->from('route_definition_version_legs as source_leg')->whereColumn('source_leg.route_definition_version_leg_id', 'plan_leg.source_route_definition_version_leg_id')->whereColumn('source_leg.hq_id', 'plan_leg.hq_id'))->doesntExist();
    }

    public function consignmentOriginatesAt(string $consignmentId, string $nodeId): bool
    {
        return DB::table('consignments')->where(['consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId])->exists();
    }

    public function planTouchesNode(string $planId, string $nodeId): bool
    {
        return RoutePlanLegRecord::query()->toBase()->where('route_plan_id', $planId)->where(fn($query) => $query->where('origin_node_id', $nodeId)->orWhere('destination_node_id', $nodeId))->exists();
    }

    public function consignment(string $hqId, string $consignmentId): ?object
    {
        return DB::table('consignments')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->first();
    }

    public function definition(string $hqId, string $definitionId): ?object
    {
        return RouteDefinitionRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_definition_id' => $definitionId])->first();
    }

    public function routeVersionStatus(string $hqId, ?string $versionId): ?string
    {
        return RouteDefinitionVersionRecord::query()->toBase()->where(['hq_id' => $hqId, 'route_definition_version_id' => $versionId])->value('status');
    }

    public function resolutionEvidence(string $hqId, string $planId): ?object
    {
        return RoutePlanResolutionEvidenceRecord::query()->toBase()->from('route_plan_resolution_evidence as e')->join('coverage_policies as policy', 'policy.coverage_policy_id', '=', 'e.coverage_policy_id')->join('coverage_policy_versions as coverage_version', 'coverage_version.coverage_policy_version_id', '=', 'e.coverage_policy_version_id')->join('nodes as gateway', 'gateway.node_id', '=', 'e.destination_gateway_node_id')->where(['e.hq_id' => $hqId, 'e.route_plan_id' => $planId])->first([
            'e.*',
            'policy.policy_code',
            'policy.policy_title',
            'coverage_version.version_number as coverage_version_number',
            'coverage_version.status as coverage_version_status',
            'gateway.node_code as gateway_code',
            'gateway.node_title as gateway_title',
        ]);
    }

    public function planLegs(string $planId): array
    {
        return RoutePlanLegRecord::query()->toBase()->from('route_plan_legs as leg')->join('nodes as origin', function ($join): void {
            $join->on('origin.hq_id', '=', 'leg.hq_id')->on('origin.node_id', '=', 'leg.origin_node_id');
        })->join('nodes as destination', function ($join): void {
            $join->on('destination.hq_id', '=', 'leg.hq_id')->on('destination.node_id', '=', 'leg.destination_node_id');
        })->where('leg.route_plan_id', $planId)->orderBy('leg.leg_order')->get([
            'leg.*',
            'origin.node_code as origin_node_code',
            'origin.node_title as origin_node_title',
            'destination.node_code as destination_node_code',
            'destination.node_title as destination_node_title',
        ])->all();
    }

    public function insertPlan(array $attributes): void
    {
        RoutePlanRecord::query()->toBase()->insert($attributes);
    }

    public function insertLeg(array $attributes): void
    {
        RoutePlanLegRecord::query()->toBase()->insert($attributes);
    }

    public function insertEvidence(array $attributes): void
    {
        RoutePlanResolutionEvidenceRecord::query()->toBase()->insert($attributes);
    }

    public function updateLeg(string $id, array $changes): void
    {
        RoutePlanLegRecord::query()->toBase()->where('route_plan_leg_id', $id)->update($changes);
    }

    public function updatePlan(string $id, array $changes): void
    {
        RoutePlanRecord::query()->toBase()->where('route_plan_id', $id)->update($changes);
    }
}
