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
    ) {}

    /** @return array<string,mixed> */
    public function plan(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $definitionId, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $id = $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $definitionId, $correlationId): string {
            $consignment = DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId])->first();
            if ($consignment === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $existing = DB::table('route_plans')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->whereIn('status', ['PLANNED', 'IN_PROGRESS'])->first();
            if ($existing !== null) return (string) $existing->route_plan_id;
            $definition = DB::table('route_definitions')->where(['hq_id' => $actor->hqId, 'route_definition_id' => $definitionId, 'status' => 'ACTIVE'])->first();
            $legs = DB::table('route_definition_legs')->where(['hq_id' => $actor->hqId, 'route_definition_id' => $definitionId, 'status' => 'ACTIVE'])->orderBy('leg_order')->get();
            if ($definition === null || $legs->isEmpty() || (string) $legs->first()->origin_node_id !== $nodeId || (string) $legs->last()->destination_node_id !== (string) $consignment->delivery_node_id) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The route does not connect the Consignment pickup and delivery nodes.');
            $id = (string) Str::uuid();
            DB::table('route_plans')->insert(['route_plan_id' => $id, 'hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'route_definition_id' => $definitionId, 'status' => 'PLANNED', 'active_slot' => hash('sha256', "{$actor->hqId}|{$consignmentId}|ACTIVE"), 'version' => 1, 'created_by' => $actor->userId, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($legs as $leg) DB::table('route_plan_legs')->insert(['route_plan_leg_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'route_plan_id' => $id, 'source_route_definition_leg_id' => $leg->route_definition_leg_id, 'leg_order' => $leg->leg_order, 'origin_node_id' => $leg->origin_node_id, 'destination_node_id' => $leg->destination_node_id, 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
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
    public function createRun(AuthenticatedPrincipal $actor, string $nodeId, string $legId, string $driverId, string $vehicleId, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $id = $this->transactions->run(function () use ($actor, $nodeId, $legId, $driverId, $vehicleId, $correlationId): string {
            $leg = DB::table('route_plan_legs')->where(['hq_id' => $actor->hqId, 'route_plan_leg_id' => $legId, 'origin_node_id' => $nodeId, 'status' => 'ROUTED'])->first();
            if ($leg === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only the currently routed leg can create a Transport Run.');
            $driver = DB::table('drivers as d')->where(['d.hq_id' => $actor->hqId, 'd.driver_id' => $driverId, 'd.status' => 'ACTIVE', 'd.availability_status' => 'AVAILABLE'])->whereExists(fn ($q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', 'LINEHAUL'))->exists();
            $vehicle = DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'vehicle_id' => $vehicleId, 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE'])->exists();
            if (! $driver || ! $vehicle) throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active available linehaul driver and vehicle are required.');
            $existing = DB::table('transport_runs')->where(['hq_id' => $actor->hqId, 'route_plan_leg_id' => $legId])->whereNot('status', 'CLOSED')->first();
            if ($existing !== null) return (string) $existing->transport_run_id;
            $id = (string) Str::uuid();
            DB::table('transport_runs')->insert(['transport_run_id' => $id, 'hq_id' => $actor->hqId, 'transport_run_number' => 'TR-'.now()->format('ymd').'-'.strtoupper(substr(str_replace('-', '', $id), 0, 8)), 'route_plan_leg_id' => $legId, 'origin_node_id' => $leg->origin_node_id, 'destination_node_id' => $leg->destination_node_id, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId, 'status' => 'CREATED', 'version' => 1, 'created_by' => $actor->userId, 'created_at' => now(), 'updated_at' => now()]);
            $this->record($actor, 'TRANSPORT_RUN_CREATED', 'TRANSPORT_RUN', $id, $this->consignmentForLeg($legId), 'CREATED', $correlationId);
            return $id;
        });
        return $this->run($actor, $nodeId, $id);
    }

    /** @param list<string> $parcelIds @return array<string,mixed> */
    public function load(AuthenticatedPrincipal $actor, string $nodeId, string $runId, array $parcelIds, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $parcelIds, $expected, $correlationId): void {
            $run = $this->lockedRun($actor, $nodeId, $runId); $this->version($run, $expected, 'Transport Run');
            if (! in_array($run->status, ['CREATED', 'LOADED'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Transport Run cannot be loaded in its current state.');
            foreach ($parcelIds as $parcelId) {
                $parcel = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'parcel_id' => $parcelId, 'current_status' => 'OF', 'active_route_plan_leg_id' => $run->route_plan_leg_id])->first();
                if ($parcel === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every loaded Parcel must be outbound-confirmed for this route leg.');
                DB::table('transport_run_parcels')->updateOrInsert(['transport_run_id' => $runId, 'parcel_id' => $parcelId], ['transport_run_parcel_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'loaded_at' => now(), 'loaded_by' => $actor->userId]);
                DB::table('parcels')->where('parcel_id', $parcelId)->update(['active_transport_run_id' => $runId]);
            }
            DB::table('transport_runs')->where('transport_run_id', $runId)->update(['status' => 'LOADED', 'version' => $expected + 1, 'updated_at' => now()]);
            $this->record($actor, 'TRANSPORT_RUN_LOADED', 'TRANSPORT_RUN', $runId, $this->consignmentForLeg((string) $run->route_plan_leg_id), 'LOADED', $correlationId);
        });
        return $this->run($actor, $nodeId, $runId);
    }

    /** @return array<string,mixed> */
    public function depart(AuthenticatedPrincipal $actor, string $nodeId, string $runId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $expected, $correlationId): void {
            $run = $this->lockedRun($actor, $nodeId, $runId); $this->version($run, $expected, 'Transport Run');
            if ($run->status !== 'LOADED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Transport Run must be loaded before departure.');
            $consignmentId = $this->consignmentForLeg((string) $run->route_plan_leg_id);
            $loaded = DB::table('transport_run_parcels')->where('transport_run_id', $runId)->count();
            $parcelCount = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->count();
            if ($loaded !== $parcelCount) throw new ApiException(ApiErrorCode::ValidationError, 422, 'All Consignment Parcels must be loaded before departure.');
            $this->lifecycle->transition($actor, $consignmentId, 'OF', 'OS', 'TRANSPORT_DEPARTED', null, 'TRANSPORT_RUN', $runId, $correlationId, (string) $run->driver_id, transportRunId: $runId);
            DB::table('transport_runs')->where('transport_run_id', $runId)->update(['status' => 'DEPARTED', 'version' => $expected + 1, 'departed_at' => now(), 'updated_at' => now()]);
            DB::table('route_plan_legs')->where('route_plan_leg_id', $run->route_plan_leg_id)->update(['status' => 'IN_TRANSIT', 'updated_at' => now()]);
            DB::table('drivers')->where('driver_id', $run->driver_id)->update(['availability_status' => 'ON_MISSION']);
            DB::table('vehicles')->where('vehicle_id', $run->vehicle_id)->update(['availability_status' => 'ON_MISSION']);
        });
        return $this->run($actor, $nodeId, $runId);
    }

    /** @return array<string,mixed> */
    public function arrive(AuthenticatedPrincipal $actor, string $nodeId, string $runId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $expected, $correlationId): void {
            $run = DB::table('transport_runs')->where(['hq_id' => $actor->hqId, 'transport_run_id' => $runId, 'destination_node_id' => $nodeId])->lockForUpdate()->first();
            if ($run === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $this->version($run, $expected, 'Transport Run');
            if ($run->status !== 'DEPARTED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a departed Transport Run can arrive.');
            DB::table('transport_runs')->where('transport_run_id', $runId)->update(['status' => 'ARRIVED', 'version' => $expected + 1, 'arrived_at' => now(), 'updated_at' => now()]);
            DB::table('route_plan_legs')->where('route_plan_leg_id', $run->route_plan_leg_id)->update(['status' => 'ARRIVED', 'updated_at' => now()]);
            $this->record($actor, 'TRANSPORT_RUN_ARRIVED', 'TRANSPORT_RUN', $runId, $this->consignmentForLeg((string) $run->route_plan_leg_id), 'ARRIVED', $correlationId);
        });
        return $this->run($actor, $nodeId, $runId, true);
    }

    /** @return array<string,mixed> */
    public function close(AuthenticatedPrincipal $actor, string $nodeId, string $runId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $expected, $correlationId): void {
            $run = DB::table('transport_runs')->where(['hq_id' => $actor->hqId, 'transport_run_id' => $runId, 'destination_node_id' => $nodeId])->lockForUpdate()->first();
            if ($run === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $this->version($run, $expected, 'Transport Run');
            if ($run->status !== 'ARRIVED' || ! DB::table('route_plan_legs')->where(['route_plan_leg_id' => $run->route_plan_leg_id, 'status' => 'RECEIVED'])->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Inbound reception must complete before the Transport Run closes.');
            DB::table('transport_runs')->where('transport_run_id', $runId)->update(['status' => 'CLOSED', 'version' => $expected + 1, 'closed_at' => now(), 'updated_at' => now()]);
            DB::table('drivers')->where('driver_id', $run->driver_id)->update(['availability_status' => 'AVAILABLE']);
            DB::table('vehicles')->where('vehicle_id', $run->vehicle_id)->update(['availability_status' => 'AVAILABLE']);
            $this->record($actor, 'TRANSPORT_RUN_CLOSED', 'TRANSPORT_RUN', $runId, $this->consignmentForLeg((string) $run->route_plan_leg_id), 'CLOSED', $correlationId);
        });
        return $this->run($actor, $nodeId, $runId, true);
    }

    /** @return array<string,mixed> */
    public function routePlan(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $plan = DB::table('route_plans')->where(['hq_id' => $actor->hqId, 'route_plan_id' => $id])->first();
        if ($plan === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $legs = DB::table('route_plan_legs')->where('route_plan_id', $id)->orderBy('leg_order')->get()->map(fn ($leg): array => ['route_plan_leg_id' => (string) $leg->route_plan_leg_id, 'leg_order' => (int) $leg->leg_order, 'origin_node_id' => (string) $leg->origin_node_id, 'destination_node_id' => (string) $leg->destination_node_id, 'status' => (string) $leg->status, 'routed_at' => $leg->routed_at, 'received_at' => $leg->received_at])->all();
        return ['route_plan_id' => (string) $plan->route_plan_id, 'consignment_id' => (string) $plan->consignment_id, 'route_definition_id' => (string) $plan->route_definition_id, 'status' => (string) $plan->status, 'version' => (int) $plan->version, 'legs' => $legs];
    }

    /** @return array<string,mixed> */
    public function run(AuthenticatedPrincipal $actor, string $nodeId, string $id, bool $destination = false): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $query = DB::table('transport_runs')->where(['hq_id' => $actor->hqId, 'transport_run_id' => $id]);
        $query->where($destination ? 'destination_node_id' : 'origin_node_id', $nodeId);
        $run = $query->first(); if ($run === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return ['transport_run_id' => (string) $run->transport_run_id, 'transport_run_number' => (string) $run->transport_run_number, 'route_plan_leg_id' => (string) $run->route_plan_leg_id, 'origin_node_id' => (string) $run->origin_node_id, 'destination_node_id' => (string) $run->destination_node_id, 'driver_id' => (string) $run->driver_id, 'vehicle_id' => (string) $run->vehicle_id, 'status' => (string) $run->status, 'version' => (int) $run->version, 'parcel_ids' => DB::table('transport_run_parcels')->where('transport_run_id', $id)->pluck('parcel_id')->map(fn ($v): string => (string) $v)->all(), 'departed_at' => $run->departed_at, 'arrived_at' => $run->arrived_at, 'closed_at' => $run->closed_at];
    }

    private function lockedRun(AuthenticatedPrincipal $actor, string $nodeId, string $id): object { $run=DB::table('transport_runs')->where(['hq_id'=>$actor->hqId,'origin_node_id'=>$nodeId,'transport_run_id'=>$id])->lockForUpdate()->first();if($run===null)throw new ApiException(ApiErrorCode::ResourceNotFound,404,'Resource not found.');return $run; }
    private function consignmentForLeg(string $legId): string { return (string) DB::table('route_plan_legs as l')->join('route_plans as p','p.route_plan_id','=','l.route_plan_id')->where('l.route_plan_leg_id',$legId)->value('p.consignment_id'); }
    private function version(object $row, int $expected, string $label): void { if((int)$row->version!==$expected)throw new ApiException(ApiErrorCode::VersionConflict,409,"The {$label} version is stale.",details:['current_version'=>(int)$row->version]); }
    private function record(AuthenticatedPrincipal $actor,string $command,string $type,string $id,string $consignmentId,string $status,string $correlationId): void { $this->audit->write($actor->hqId,$actor->userId,$command,$type,$id,$correlationId);$this->outbox->write($actor->hqId,$type,$id,'operations.command.executed',$correlationId,['command'=>$command,'resource_id'=>$id,'consignment_id'=>$consignmentId,'status'=>$status]); }
    private function access(AuthenticatedPrincipal $actor,string $nodeId,string $permission): void { if($actor->hqId===null)throw new ApiException(ApiErrorCode::TenantAccessDenied,403,'Access denied.');$c=$this->authorization->resolve($actor);if(!collect($c['module_entitlements'])->contains(fn($e)=>$e['module_code']==='LiveOperations'&&$e['status']==='ENABLED'))throw new ApiException(ApiErrorCode::EntitlementDisabled,403,'Access denied.');if(!in_array($permission,$c['permissions'],true))throw new ApiException(ApiErrorCode::PermissionDenied,403,'Access denied.');if(!in_array($nodeId,$c['accessible_node_ids'],true))throw new ApiException(ApiErrorCode::ScopeAccessDenied,403,'Access denied.'); }
}
