<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class MovementService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private ParcelLifecycleService $lifecycle,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private CoveragePolicyService $coverage,
        private RouteDefinitionService $routes,
    ) {}

    /** @return array<string,mixed> */
    public function plan(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $id = $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $correlationId): string {
            $consignment = DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId])->lockForUpdate()->first();
            if ($consignment === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $existing = DB::table('route_plans')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->first();
            if ($existing !== null) return (string) $existing->route_plan_id;

            $resolvedAt = now();
            $resolutionInput = $this->destinationResolutionInput($consignment);
            $coverage = $this->coverage->resolve(
                (string) $actor->hqId,
                'DESTINATION_GATEWAY',
                $resolutionInput,
                $consignment->service_offering_version_id === null ? null : (string) $consignment->service_offering_version_id,
                $resolvedAt,
            );
            $route = $this->routes->resolve(
                (string) $actor->hqId,
                'TRUNK',
                $nodeId,
                (string) $coverage['target_node_id'],
                $consignment->service_offering_version_id === null ? null : (string) $consignment->service_offering_version_id,
                $resolvedAt,
            );
            $legs = DB::table('route_definition_version_legs')->where([
                'hq_id' => $actor->hqId,
                'route_definition_version_id' => $route['route_definition_version_id'],
            ])->orderBy('leg_order')->get();
            if ($legs->isEmpty()) throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The published Route Version is unavailable.');

            $definitionId = (string) $route['route_definition_id'];
            $orderedLegEvidence = $legs->map(fn ($leg): array => [
                'route_definition_version_leg_id' => (string) $leg->route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ])->all();
            $id = (string) Str::uuid();
            DB::table('route_plans')->insert(['route_plan_id' => $id, 'hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'route_definition_id' => $definitionId, 'route_definition_version_id' => $route['route_definition_version_id'], 'status' => 'PLANNED', 'active_slot' => hash('sha256', "{$actor->hqId}|{$consignmentId}|ACTIVE"), 'version' => 1, 'created_by' => $actor->userId, 'created_at' => $resolvedAt, 'updated_at' => $resolvedAt]);
            foreach ($legs as $leg) {
                $legacyLegId = DB::table('route_definition_legs')->where([
                    'hq_id' => $actor->hqId,
                    'route_definition_id' => $definitionId,
                    'leg_order' => $leg->leg_order,
                    'status' => 'ACTIVE',
                ])->value('route_definition_leg_id');
                if ($legacyLegId === null) throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The published Route Version leg snapshot is unavailable.');
                DB::table('route_plan_legs')->insert(['route_plan_leg_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'route_plan_id' => $id, 'source_route_definition_leg_id' => $legacyLegId, 'source_route_definition_version_leg_id' => $leg->route_definition_version_leg_id, 'leg_order' => $leg->leg_order, 'origin_node_id' => $leg->origin_node_id, 'destination_node_id' => $leg->destination_node_id, 'status' => 'PENDING', 'created_at' => $resolvedAt, 'updated_at' => $resolvedAt]);
            }
            $matched = (array) $coverage['matched_evidence'];
            DB::table('route_plan_resolution_evidence')->insert([
                'resolution_evidence_id' => (string) Str::uuid(),
                'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId,
                'route_plan_id' => $id,
                'coverage_policy_id' => $coverage['coverage_policy_id'],
                'coverage_policy_version_id' => $coverage['coverage_policy_version_id'],
                'coverage_rule_id' => $coverage['coverage_rule_id'],
                'coverage_criterion_type' => $coverage['criterion_type'],
                'coverage_priority' => $coverage['priority'],
                'resolution_input' => json_encode($coverage['input'], JSON_THROW_ON_ERROR),
                'matched_geography_evidence' => isset($matched['geography']) ? json_encode($matched['geography'], JSON_THROW_ON_ERROR) : null,
                'matched_postal_evidence' => isset($matched['postal']) ? json_encode($matched['postal'], JSON_THROW_ON_ERROR) : null,
                'matched_geometry_evidence' => isset($matched['geometry']) ? json_encode($matched['geometry'], JSON_THROW_ON_ERROR) : null,
                'destination_gateway_node_id' => $coverage['target_node_id'],
                'route_definition_id' => $definitionId,
                'route_definition_version_id' => $route['route_definition_version_id'],
                'route_purpose' => 'TRUNK',
                'ordered_route_legs' => json_encode($orderedLegEvidence, JSON_THROW_ON_ERROR),
                'offering_version_id' => $consignment->service_offering_version_id,
                'resolved_at' => $resolvedAt,
                'created_at' => $resolvedAt,
            ]);
            $this->record($actor, 'ROUTE_PLAN_CREATED', 'ROUTE_PLAN', $id, $consignmentId, 'PLANNED', $correlationId);
            return $id;
        });
        return $this->routePlan($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function cluster(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, int $expectedPlanVersion, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $expectedPlanVersion, $correlationId): void {
            $plan = DB::table('route_plans')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->lockForUpdate()->first();
            if ($plan === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active Route Plan is required.');
            $this->version($plan, $expectedPlanVersion, 'Route Plan');
            $this->assertPlanConfigurationAvailable($plan);
            $leg = DB::table('route_plan_legs')->where(['hq_id' => $actor->hqId, 'route_plan_id' => $plan->route_plan_id, 'origin_node_id' => $nodeId, 'status' => 'PENDING'])->orderBy('leg_order')->lockForUpdate()->first();
            if ($leg === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'No next route leg is available at this node.');
            if ((int) $leg->leg_order > 1 && ! DB::table('route_plan_legs')->where(['route_plan_id' => $plan->route_plan_id, 'leg_order' => (int) $leg->leg_order - 1, 'status' => 'RECEIVED'])->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The preceding route leg has not been received.');
            $this->lifecycle->transition($actor, $consignmentId, 'IR', 'ROU', 'ROUTE_CLUSTERED', $nodeId, 'NODE', $nodeId, $correlationId, routePlanId: (string) $plan->route_plan_id, routePlanLegId: (string) $leg->route_plan_leg_id);
            DB::table('parcels')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->update(['active_route_plan_id' => $plan->route_plan_id, 'active_route_plan_leg_id' => $leg->route_plan_leg_id]);
            DB::table('route_plan_legs')->where('route_plan_leg_id', $leg->route_plan_leg_id)->update(['status' => 'ROUTED', 'routed_at' => now(), 'updated_at' => now()]);
            DB::table('route_plans')->where('route_plan_id', $plan->route_plan_id)->update(['status' => 'IN_PROGRESS', 'version' => $expectedPlanVersion + 1, 'updated_at' => now()]);
        });
        $planId = (string) DB::table('route_plans')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->value('route_plan_id');
        return $this->routePlan($actor, $nodeId, $planId);
    }

    /** @return array<string,mixed> */
    public function routePlan(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $plan = DB::table('route_plans')->where(['hq_id' => $actor->hqId, 'route_plan_id' => $id])->first();
        if ($plan === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        if (! $this->planVisibleAtNode((string) $plan->route_plan_id, (string) $plan->consignment_id, $nodeId)) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $this->routePlanItem($plan);
    }

    /** @return list<array<string,mixed>> */
    public function listRoutePlans(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        return DB::table('route_plans as p')->join('consignments as c', function ($join): void {
            $join->on('c.hq_id', '=', 'p.hq_id')->on('c.consignment_id', '=', 'p.consignment_id');
        })->where('p.hq_id', $actor->hqId)->where(function ($query) use ($nodeId): void {
            $query->where('c.pickup_node_id', $nodeId)->orWhereExists(fn ($legs) => $legs->selectRaw('1')->from('route_plan_legs as visible_leg')->whereColumn('visible_leg.route_plan_id', 'p.route_plan_id')->where(fn ($nodes) => $nodes->where('visible_leg.origin_node_id', $nodeId)->orWhere('visible_leg.destination_node_id', $nodeId)));
        })->orderByDesc('p.created_at')->get(['p.*'])->map(fn ($plan): array => $this->routePlanItem($plan))->all();
    }

    /** @return array<string,mixed> */
    private function destinationResolutionInput(object $consignment): array
    {
        $input = [];
        if ($consignment->receiver_city_id !== null) {
            $input['city_id'] = (string) $consignment->receiver_city_id;
            $provinceId = DB::table('cities')->where([
                'city_id' => $consignment->receiver_city_id,
                'is_active' => true,
            ])->value('province_id');
            if ($provinceId !== null) $input['province_id'] = (string) $provinceId;
        }
        if (preg_match('/^\d{10}$/', (string) $consignment->receiver_postal_code) === 1) {
            $input['postal_code'] = (string) $consignment->receiver_postal_code;
        }
        if ($consignment->receiver_latitude !== null && $consignment->receiver_longitude !== null) {
            $input['latitude'] = (float) $consignment->receiver_latitude;
            $input['longitude'] = (float) $consignment->receiver_longitude;
        }
        if ($input === []) throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'Canonical destination geography is unavailable for coverage resolution.');

        return $input;
    }

    private function assertPlanConfigurationAvailable(object $plan): void
    {
        $evidence = DB::table('route_plan_resolution_evidence')->where([
            'hq_id' => $plan->hq_id,
            'route_plan_id' => $plan->route_plan_id,
            'route_definition_version_id' => $plan->route_definition_version_id,
        ])->first();
        $version = $plan->route_definition_version_id === null ? null : DB::table('route_definition_versions')->where([
            'hq_id' => $plan->hq_id,
            'route_definition_id' => $plan->route_definition_id,
            'route_definition_version_id' => $plan->route_definition_version_id,
        ])->first();
        $coverageVersionStatus = $evidence === null ? null : DB::table('coverage_policy_versions')->where([
            'hq_id' => $plan->hq_id,
            'coverage_policy_version_id' => $evidence->coverage_policy_version_id,
        ])->value('status');
        $legsAvailable = DB::table('route_plan_legs as plan_leg')->where('plan_leg.route_plan_id', $plan->route_plan_id)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('route_definition_version_legs as source_leg')->whereColumn('source_leg.route_definition_version_leg_id', 'plan_leg.source_route_definition_version_leg_id')->whereColumn('source_leg.hq_id', 'plan_leg.hq_id'))
            ->doesntExist();
        if ($evidence === null || $version === null
            || ! in_array($coverageVersionStatus, ['PUBLISHED', 'SUPERSEDED'], true)
            || ! in_array($version->status, ['PUBLISHED', 'SUPERSEDED'], true)
            || ! $legsAvailable) {
            throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The Route Plan configuration snapshot is unavailable.');
        }
    }

    private function planVisibleAtNode(string $planId, string $consignmentId, string $nodeId): bool
    {
        return DB::table('consignments')->where(['consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId])->exists()
            || DB::table('route_plan_legs')->where('route_plan_id', $planId)
                ->where(fn ($query) => $query->where('origin_node_id', $nodeId)->orWhere('destination_node_id', $nodeId))->exists();
    }

    /** @return array<string,mixed> */
    private function routePlanItem(object $plan): array
    {
        $consignment = DB::table('consignments')->where(['hq_id' => $plan->hq_id, 'consignment_id' => $plan->consignment_id])->first();
        $definition = DB::table('route_definitions')->where(['hq_id' => $plan->hq_id, 'route_definition_id' => $plan->route_definition_id])->first();
        $versionStatus = $plan->route_definition_version_id === null ? null : DB::table('route_definition_versions')->where([
            'hq_id' => $plan->hq_id,
            'route_definition_version_id' => $plan->route_definition_version_id,
        ])->value('status');
        $evidence = DB::table('route_plan_resolution_evidence as e')
            ->join('coverage_policies as policy', 'policy.coverage_policy_id', '=', 'e.coverage_policy_id')
            ->join('coverage_policy_versions as coverage_version', 'coverage_version.coverage_policy_version_id', '=', 'e.coverage_policy_version_id')
            ->join('nodes as gateway', 'gateway.node_id', '=', 'e.destination_gateway_node_id')
            ->where(['e.hq_id' => $plan->hq_id, 'e.route_plan_id' => $plan->route_plan_id])
            ->first(['e.*', 'policy.policy_code', 'policy.policy_title', 'coverage_version.version_number as coverage_version_number', 'coverage_version.status as coverage_version_status', 'gateway.node_code as gateway_code', 'gateway.node_title as gateway_title']);
        $legs = DB::table('route_plan_legs as leg')
            ->join('nodes as origin', function ($join): void { $join->on('origin.hq_id', '=', 'leg.hq_id')->on('origin.node_id', '=', 'leg.origin_node_id'); })
            ->join('nodes as destination', function ($join): void { $join->on('destination.hq_id', '=', 'leg.hq_id')->on('destination.node_id', '=', 'leg.destination_node_id'); })
            ->where('leg.route_plan_id', $plan->route_plan_id)->orderBy('leg.leg_order')
            ->get(['leg.*', 'origin.node_code as origin_node_code', 'origin.node_title as origin_node_title', 'destination.node_code as destination_node_code', 'destination.node_title as destination_node_title'])
            ->map(fn ($leg): array => [
                'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
                'source_route_definition_version_leg_id' => $leg->source_route_definition_version_leg_id === null ? null : (string) $leg->source_route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node' => ['node_id' => (string) $leg->origin_node_id, 'node_code' => (string) $leg->origin_node_code, 'node_title' => (string) $leg->origin_node_title],
                'destination_node' => ['node_id' => (string) $leg->destination_node_id, 'node_code' => (string) $leg->destination_node_code, 'node_title' => (string) $leg->destination_node_title],
                'status' => (string) $leg->status,
                'routed_at' => $leg->routed_at,
                'received_at' => $leg->received_at,
            ])->all();

        $configurationState = $evidence === null || $versionStatus === null
            ? 'UNAVAILABLE'
            : ($versionStatus === 'SUPERSEDED' || $evidence->coverage_version_status === 'SUPERSEDED'
                ? 'STALE'
                : ($versionStatus === 'PUBLISHED' && $evidence->coverage_version_status === 'PUBLISHED' ? 'AVAILABLE' : 'UNAVAILABLE'));

        return [
            'route_plan_id' => (string) $plan->route_plan_id,
            'consignment_id' => (string) $plan->consignment_id,
            'consignment_number' => $consignment === null ? null : (string) $consignment->consignment_number,
            'route_definition_id' => (string) $plan->route_definition_id,
            'route_definition_version_id' => $plan->route_definition_version_id === null ? null : (string) $plan->route_definition_version_id,
            'route_code' => $definition === null ? null : (string) $definition->route_code,
            'route_title' => $definition === null ? null : (string) $definition->route_title,
            'status' => (string) $plan->status,
            'configuration_state' => $configurationState,
            'version' => (int) $plan->version,
            'created_at' => $plan->created_at,
            'resolution_evidence' => $evidence === null ? null : [
                'coverage_policy_id' => (string) $evidence->coverage_policy_id,
                'coverage_policy_code' => (string) $evidence->policy_code,
                'coverage_policy_title' => (string) $evidence->policy_title,
                'coverage_policy_version_id' => (string) $evidence->coverage_policy_version_id,
                'coverage_policy_version_number' => (int) $evidence->coverage_version_number,
                'coverage_rule_id' => (string) $evidence->coverage_rule_id,
                'coverage_criterion_type' => (string) $evidence->coverage_criterion_type,
                'coverage_priority' => (int) $evidence->coverage_priority,
                'resolution_input' => json_decode((string) $evidence->resolution_input, true, 512, JSON_THROW_ON_ERROR),
                'matched_geography_evidence' => $this->jsonObject($evidence->matched_geography_evidence),
                'matched_postal_evidence' => $this->jsonObject($evidence->matched_postal_evidence),
                'matched_geometry_evidence' => $this->jsonObject($evidence->matched_geometry_evidence),
                'destination_gateway' => ['node_id' => (string) $evidence->destination_gateway_node_id, 'node_code' => (string) $evidence->gateway_code, 'node_title' => (string) $evidence->gateway_title],
                'route_definition_id' => (string) $evidence->route_definition_id,
                'route_definition_version_id' => (string) $evidence->route_definition_version_id,
                'route_purpose' => (string) $evidence->route_purpose,
                'ordered_route_legs' => json_decode((string) $evidence->ordered_route_legs, true, 512, JSON_THROW_ON_ERROR),
                'offering_version_id' => $evidence->offering_version_id === null ? null : (string) $evidence->offering_version_id,
                'resolved_at' => $evidence->resolved_at,
            ],
            'legs' => $legs,
        ];
    }

    /** @return array<string,mixed>|null */
    private function jsonObject(mixed $value): ?array
    {
        return $value === null ? null : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }

    private function version(object $row, int $expected, string $label): void { if((int)$row->version!==$expected)throw new ApiException(ApiErrorCode::VersionConflict,409,"The {$label} version is stale.",details:['current_version'=>(int)$row->version]); }
    private function record(AuthenticatedPrincipal $actor,string $command,string $type,string $id,string $consignmentId,string $status,string $correlationId): void { $this->audit->write($actor->hqId,$actor->userId,$command,$type,$id,$correlationId);$this->outbox->write($actor->hqId,$type,$id,'operations.command.executed',$correlationId,['command'=>$command,'resource_id'=>$id,'consignment_id'=>$consignmentId,'status'=>$status]); }
    private function access(AuthenticatedPrincipal $actor,string $nodeId,string $permission): void { if($actor->hqId===null)throw new ApiException(ApiErrorCode::TenantAccessDenied,403,'Access denied.');$c=$this->authorization->resolve($actor);if(!collect($c['module_entitlements'])->contains(fn($e)=>$e['module_code']==='LiveOperations'&&$e['status']==='ENABLED'))throw new ApiException(ApiErrorCode::EntitlementDisabled,403,'Access denied.');if(!in_array($permission,$c['permissions'],true))throw new ApiException(ApiErrorCode::PermissionDenied,403,'Access denied.');if(!in_array($nodeId,$c['accessible_node_ids'],true))throw new ApiException(ApiErrorCode::ScopeAccessDenied,403,'Access denied.'); }
}
