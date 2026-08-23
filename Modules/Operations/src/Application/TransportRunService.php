<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class TransportRunService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private ParcelLifecycleService $lifecycle,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string,mixed> $filters */
    public function list(AuthenticatedPrincipal $actor, string $nodeId, array $filters): LengthAwarePaginator
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $query = DB::table('transport_runs as r')
            ->join('nodes as origin', 'origin.node_id', '=', 'r.origin_node_id')
            ->join('nodes as destination', 'destination.node_id', '=', 'r.destination_node_id')
            ->join('drivers as driver', 'driver.driver_id', '=', 'r.driver_id')
            ->join('vehicles as vehicle', 'vehicle.vehicle_id', '=', 'r.vehicle_id')
            ->where('r.hq_id', $actor->hqId)
            ->where(fn ($scope) => $scope->where('r.origin_node_id', $nodeId)->orWhere('r.destination_node_id', $nodeId));
        if (($filters['status'] ?? '') !== '') $query->where('r.status', $filters['status']);
        if (($filters['search'] ?? '') !== '') {
            $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn ($search) => $search->where('r.transport_run_number', 'like', $term)
                ->orWhere('driver.display_name', 'like', $term)->orWhere('vehicle.vehicle_code', 'like', $term));
        }
        $page = $query->orderByDesc('r.created_at')->paginate(
            min(100, max(1, (int) ($filters['per_page'] ?? 20))),
            ['r.*', 'origin.node_code as origin_code', 'origin.node_title as origin_title', 'destination.node_code as destination_code', 'destination.node_title as destination_title', 'driver.driver_code', 'driver.display_name as driver_name', 'vehicle.vehicle_code', 'vehicle.plate_number'],
            'page',
            max(1, (int) ($filters['page'] ?? 1)),
        );
        $page->setCollection($page->getCollection()->map(fn ($row): array => $this->summary($row)));

        return $page;
    }

    /** @return list<array<string,mixed>> */
    public function candidates(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        return DB::table('route_plan_legs as leg')
            ->join('route_plans as plan', 'plan.route_plan_id', '=', 'leg.route_plan_id')
            ->join('consignments as consignment', 'consignment.consignment_id', '=', 'plan.consignment_id')
            ->join('nodes as destination', 'destination.node_id', '=', 'leg.destination_node_id')
            ->leftJoin('route_definition_version_legs as source_leg', 'source_leg.route_definition_version_leg_id', '=', 'leg.source_route_definition_version_leg_id')
            ->leftJoin('route_definition_versions as source_version', 'source_version.route_definition_version_id', '=', 'source_leg.route_definition_version_id')
            ->where(['leg.hq_id' => $actor->hqId, 'leg.origin_node_id' => $nodeId, 'leg.status' => 'ROUTED'])
            ->whereNotExists(fn ($run) => $run->selectRaw('1')->from('transport_runs')->whereColumn('transport_runs.route_plan_leg_id', 'leg.route_plan_leg_id'))
            ->orderBy('consignment.consignment_number')->get([
                'leg.route_plan_leg_id', 'leg.leg_order', 'leg.destination_node_id', 'destination.node_code as destination_code',
                'destination.node_title as destination_title', 'plan.route_plan_id', 'consignment.consignment_id',
                'consignment.consignment_number', 'source_version.status as configuration_status',
            ])->map(fn ($row): array => [
                'route_plan_leg_id' => (string) $row->route_plan_leg_id,
                'route_plan_id' => (string) $row->route_plan_id,
                'leg_order' => (int) $row->leg_order,
                'consignment_id' => (string) $row->consignment_id,
                'consignment_number' => (string) $row->consignment_number,
                'destination_node' => ['node_id' => (string) $row->destination_node_id, 'node_code' => (string) $row->destination_code, 'node_title' => (string) $row->destination_title],
                'configuration_status' => $row->configuration_status,
                'configuration_available' => $row->configuration_status === 'PUBLISHED',
            ])->all();
    }

    /** @return array<string,mixed> */
    public function create(AuthenticatedPrincipal $actor, string $nodeId, string $legId, string $driverId, string $vehicleId, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $id = $this->transactions->run(function () use ($actor, $nodeId, $legId, $driverId, $vehicleId, $correlationId): string {
            $leg = DB::table('route_plan_legs')->where(['hq_id' => $actor->hqId, 'route_plan_leg_id' => $legId, 'origin_node_id' => $nodeId])->lockForUpdate()->first();
            if ($leg === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $existing = DB::table('transport_runs')->where(['hq_id' => $actor->hqId, 'route_plan_leg_id' => $legId])->first();
            if ($existing !== null) {
                if ((string) $existing->driver_id !== $driverId || (string) $existing->vehicle_id !== $vehicleId) throw new ApiException(ApiErrorCode::Conflict, 409, 'The Route Leg already owns another Transport Run.');
                return (string) $existing->transport_run_id;
            }
            if ((string) $leg->status !== 'ROUTED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only the currently routed leg can create a Transport Run.');
            $this->assertLegOrder($leg);
            $this->assertPublishedConfiguration($leg);
            $this->assertLinehaulNode($actor->hqId, $nodeId);
            $this->assertDriver($actor->hqId, $nodeId, $driverId, 'AVAILABLE');
            $this->assertVehicle($actor->hqId, $nodeId, $vehicleId, 'AVAILABLE', (string) $leg->route_plan_id);

            $id = (string) Str::uuid();
            DB::table('transport_runs')->insert([
                'transport_run_id' => $id, 'hq_id' => $actor->hqId,
                'transport_run_number' => 'TR-'.now()->utc()->format('ymd').'-'.strtoupper(substr(str_replace('-', '', $id), 0, 8)),
                'route_plan_leg_id' => $legId, 'origin_node_id' => $leg->origin_node_id,
                'destination_node_id' => $leg->destination_node_id, 'driver_id' => $driverId,
                'vehicle_id' => $vehicleId, 'status' => 'CREATED', 'version' => 1,
                'created_by' => $actor->userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId, 'availability_status' => 'AVAILABLE'])->update(['availability_status' => 'ON_MISSION', 'updated_at' => now()]);
            DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'vehicle_id' => $vehicleId, 'availability_status' => 'AVAILABLE'])->update(['availability_status' => 'ON_MISSION', 'updated_at' => now()]);
            $this->history($actor, $id, 1, 'CREATED', null, 'CREATED', $nodeId, ['route_plan_leg_id' => $legId]);
            $this->record($actor, 'TRANSPORT_RUN_CREATED', $id, $this->consignmentForLeg($legId), 'CREATED', $correlationId);

            return $id;
        });

        return $this->get($actor, $nodeId, $id);
    }

    /** @param list<string> $parcelIds @return array<string,mixed> */
    public function load(AuthenticatedPrincipal $actor, string $nodeId, string $runId, array $parcelIds, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $parcelIds, $expected, $correlationId): void {
            $run = $this->lockedAtOrigin($actor, $nodeId, $runId); $this->expected($run, $expected);
            if ((string) $run->status !== 'CREATED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'A Transport Run can be loaded exactly once.');
            $leg = DB::table('route_plan_legs')->where('route_plan_leg_id', $run->route_plan_leg_id)->first();
            $this->assertPublishedConfiguration($leg);
            $consignmentId = $this->consignmentForLeg((string) $run->route_plan_leg_id);
            $eligible = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'current_status' => 'OF', 'active_route_plan_leg_id' => $run->route_plan_leg_id])->lockForUpdate()->pluck('parcel_id')->map(fn ($id): string => (string) $id)->sort()->values()->all();
            $requested = collect($parcelIds)->map(fn ($id): string => (string) $id)->sort()->values()->all();
            if ($eligible === [] || $eligible !== $requested) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Load must contain every outbound-confirmed Parcel for this Route Leg, once.');
            foreach ($requested as $parcelId) DB::table('transport_run_parcels')->insert(['transport_run_parcel_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'transport_run_id' => $runId, 'parcel_id' => $parcelId, 'loaded_at' => now(), 'loaded_by' => $actor->userId]);
            DB::table('parcels')->whereIn('parcel_id', $requested)->update(['active_transport_run_id' => $runId, 'updated_at' => now()]);
            DB::table('transport_runs')->where(['transport_run_id' => $runId, 'version' => $expected])->update(['status' => 'LOADED', 'version' => $expected + 1, 'updated_at' => now()]);
            $this->history($actor, $runId, 2, 'LOADED', 'CREATED', 'LOADED', $nodeId, ['parcel_ids' => $requested]);
            $this->record($actor, 'TRANSPORT_RUN_LOADED', $runId, $consignmentId, 'LOADED', $correlationId);
        });

        return $this->get($actor, $nodeId, $runId);
    }

    /** @return array<string,mixed> */
    public function depart(AuthenticatedPrincipal $actor, string $nodeId, string $runId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $expected, $correlationId): void {
            $run = $this->lockedAtOrigin($actor, $nodeId, $runId); $this->expected($run, $expected);
            if ((string) $run->status !== 'LOADED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Transport Run must be loaded before departure.');
            $leg = DB::table('route_plan_legs')->where('route_plan_leg_id', $run->route_plan_leg_id)->first();
            $this->assertLegOrder($leg); $this->assertPublishedConfiguration($leg);
            $this->assertDriver($actor->hqId, $nodeId, (string) $run->driver_id, 'ON_MISSION');
            $this->assertVehicle($actor->hqId, $nodeId, (string) $run->vehicle_id, 'ON_MISSION', (string) $leg->route_plan_id);
            $consignmentId = $this->consignmentForLeg((string) $run->route_plan_leg_id);
            $this->lifecycle->transition($actor, $consignmentId, 'OF', 'OS', 'TRANSPORT_DEPARTED', null, 'TRANSPORT_RUN', $runId, $correlationId, (string) $run->driver_id, transportRunId: $runId);
            DB::table('transport_runs')->where(['transport_run_id' => $runId, 'version' => $expected])->update(['status' => 'DEPARTED', 'version' => $expected + 1, 'departed_at' => now(), 'updated_at' => now()]);
            DB::table('route_plan_legs')->where('route_plan_leg_id', $run->route_plan_leg_id)->update(['status' => 'IN_TRANSIT', 'updated_at' => now()]);
            $this->history($actor, $runId, 3, 'DEPARTED', 'LOADED', 'DEPARTED', $nodeId);
            $this->record($actor, 'TRANSPORT_RUN_DEPARTED', $runId, $consignmentId, 'DEPARTED', $correlationId);
        });

        return $this->get($actor, $nodeId, $runId);
    }

    /** @return array<string,mixed> */
    public function arrive(AuthenticatedPrincipal $actor, string $nodeId, string $runId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $expected, $correlationId): void {
            $run = $this->lockedAtDestination($actor, $nodeId, $runId); $this->expected($run, $expected);
            if ((string) $run->status !== 'DEPARTED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a departed Transport Run can arrive.');
            DB::table('transport_runs')->where(['transport_run_id' => $runId, 'version' => $expected])->update(['status' => 'ARRIVED', 'version' => $expected + 1, 'arrived_at' => now(), 'updated_at' => now()]);
            DB::table('route_plan_legs')->where('route_plan_leg_id', $run->route_plan_leg_id)->update(['status' => 'ARRIVED', 'updated_at' => now()]);
            $this->history($actor, $runId, 4, 'ARRIVED', 'DEPARTED', 'ARRIVED', $nodeId, ['reception_completed' => false]);
            $this->record($actor, 'TRANSPORT_RUN_ARRIVED', $runId, $this->consignmentForLeg((string) $run->route_plan_leg_id), 'ARRIVED', $correlationId);
        });

        return $this->get($actor, $nodeId, $runId);
    }

    /** @return array<string,mixed> */
    public function close(AuthenticatedPrincipal $actor, string $nodeId, string $runId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $runId, $expected, $correlationId): void {
            $run = $this->lockedAtDestination($actor, $nodeId, $runId); $this->expected($run, $expected);
            $leg = DB::table('route_plan_legs')->where('route_plan_leg_id', $run->route_plan_leg_id)->lockForUpdate()->first();
            if ((string) $run->status !== 'ARRIVED' || (string) $leg->status !== 'RECEIVED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Destination reception must complete after arrival and before closing.');
            DB::table('transport_runs')->where(['transport_run_id' => $runId, 'version' => $expected])->update(['status' => 'CLOSED', 'version' => $expected + 1, 'closed_at' => now(), 'updated_at' => now()]);
            DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $run->driver_id, 'availability_status' => 'ON_MISSION'])->update(['availability_status' => 'AVAILABLE', 'updated_at' => now()]);
            DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'vehicle_id' => $run->vehicle_id, 'availability_status' => 'ON_MISSION'])->update(['availability_status' => 'AVAILABLE', 'updated_at' => now()]);
            $this->history($actor, $runId, 5, 'CLOSED', 'ARRIVED', 'CLOSED', $nodeId, ['reception_completed' => true, 'received_at' => $leg->received_at]);
            $this->record($actor, 'TRANSPORT_RUN_CLOSED', $runId, $this->consignmentForLeg((string) $run->route_plan_leg_id), 'CLOSED', $correlationId);
        });

        return $this->get($actor, $nodeId, $runId);
    }

    /** @return array<string,mixed> */
    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $run = DB::table('transport_runs as r')->join('route_plan_legs as leg', 'leg.route_plan_leg_id', '=', 'r.route_plan_leg_id')->join('route_plans as plan', 'plan.route_plan_id', '=', 'leg.route_plan_id')->join('nodes as origin', 'origin.node_id', '=', 'r.origin_node_id')->join('nodes as destination', 'destination.node_id', '=', 'r.destination_node_id')->join('drivers as driver', 'driver.driver_id', '=', 'r.driver_id')->join('vehicles as vehicle', 'vehicle.vehicle_id', '=', 'r.vehicle_id')->where(['r.hq_id' => $actor->hqId, 'r.transport_run_id' => $id])->where(fn ($scope) => $scope->where('r.origin_node_id', $nodeId)->orWhere('r.destination_node_id', $nodeId))->first(['r.*', 'leg.route_plan_id', 'leg.status as leg_status', 'leg.received_at', 'plan.consignment_id', 'origin.node_code as origin_code', 'origin.node_title as origin_title', 'destination.node_code as destination_code', 'destination.node_title as destination_title', 'driver.driver_code', 'driver.display_name as driver_name', 'vehicle.vehicle_code', 'vehicle.plate_number']);
        if ($run === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $detail = $this->summary($run);
        $detail['route_plan_id'] = (string) $run->route_plan_id;
        $detail['consignment_id'] = (string) $run->consignment_id;
        $detail['arrival'] = ['arrived_at' => $run->arrived_at, 'recorded' => $run->arrived_at !== null];
        $detail['reception'] = ['received_at' => $run->received_at, 'completed' => (string) $run->leg_status === 'RECEIVED'];
        $detail['parcels'] = DB::table('parcels as parcel')->join('consignments as consignment', 'consignment.consignment_id', '=', 'parcel.consignment_id')->leftJoin('transport_run_parcels as loaded', function ($join) use ($id): void { $join->on('loaded.parcel_id', '=', 'parcel.parcel_id')->where('loaded.transport_run_id', $id); })->where(['parcel.hq_id' => $actor->hqId, 'parcel.consignment_id' => $run->consignment_id])->orderBy('parcel.parcel_number')->get(['parcel.*', 'consignment.consignment_number', 'loaded.loaded_at'])->map(fn ($parcel): array => ['parcel_id' => (string) $parcel->parcel_id, 'parcel_number' => (string) $parcel->parcel_number, 'consignment_id' => (string) $parcel->consignment_id, 'consignment_number' => (string) $parcel->consignment_number, 'current_status' => (string) $parcel->current_status, 'current_node_id' => $parcel->current_node_id, 'current_custody_type' => (string) $parcel->current_custody_type, 'current_custodian_id' => $parcel->current_custodian_id, 'is_loaded' => $parcel->loaded_at !== null, 'loaded_at' => $parcel->loaded_at])->all();
        $detail['route_progress'] = $this->routeProgress((string) $run->route_plan_id);
        $detail['history'] = DB::table('transport_run_history')->where('transport_run_id', $id)->orderBy('event_sequence')->get()->map(fn ($event): array => ['event_type' => (string) $event->event_type, 'from_status' => $event->from_status, 'to_status' => (string) $event->to_status, 'node_id' => (string) $event->node_id, 'aggregate_version' => (int) $event->aggregate_version, 'metadata' => $event->metadata ? json_decode((string) $event->metadata, true, flags: JSON_THROW_ON_ERROR) : null, 'occurred_at' => $event->occurred_at])->all();
        $detail['permitted_actions'] = $this->permittedActions($run, $nodeId);

        return $detail;
    }

    /** @return array<string,mixed> */
    private function summary(object $run): array
    {
        return [
            'transport_run_id' => (string) $run->transport_run_id, 'transport_run_number' => (string) $run->transport_run_number,
            'route_plan_leg_id' => (string) $run->route_plan_leg_id, 'status' => (string) $run->status, 'version' => (int) $run->version,
            'origin_node' => ['node_id' => (string) $run->origin_node_id, 'node_code' => (string) $run->origin_code, 'node_title' => (string) $run->origin_title],
            'destination_node' => ['node_id' => (string) $run->destination_node_id, 'node_code' => (string) $run->destination_code, 'node_title' => (string) $run->destination_title],
            'driver' => ['driver_id' => (string) $run->driver_id, 'driver_code' => (string) $run->driver_code, 'display_name' => (string) $run->driver_name],
            'vehicle' => ['vehicle_id' => (string) $run->vehicle_id, 'vehicle_code' => (string) $run->vehicle_code, 'plate_number' => (string) $run->plate_number],
            'parcel_count' => DB::table('transport_run_parcels')->where('transport_run_id', $run->transport_run_id)->count(),
            'departed_at' => $run->departed_at, 'arrived_at' => $run->arrived_at, 'closed_at' => $run->closed_at,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function routeProgress(string $planId): array
    {
        return DB::table('route_plan_legs as leg')->join('nodes as origin', 'origin.node_id', '=', 'leg.origin_node_id')->join('nodes as destination', 'destination.node_id', '=', 'leg.destination_node_id')->where('leg.route_plan_id', $planId)->orderBy('leg.leg_order')->get(['leg.*', 'origin.node_code as origin_code', 'origin.node_title as origin_title', 'destination.node_code as destination_code', 'destination.node_title as destination_title'])->map(fn ($leg): array => ['route_plan_leg_id' => (string) $leg->route_plan_leg_id, 'leg_order' => (int) $leg->leg_order, 'status' => (string) $leg->status, 'origin_node' => ['node_id' => (string) $leg->origin_node_id, 'node_code' => (string) $leg->origin_code, 'node_title' => (string) $leg->origin_title], 'destination_node' => ['node_id' => (string) $leg->destination_node_id, 'node_code' => (string) $leg->destination_code, 'node_title' => (string) $leg->destination_title], 'routed_at' => $leg->routed_at, 'received_at' => $leg->received_at])->all();
    }

    /** @return list<string> */
    private function permittedActions(object $run, string $nodeId): array
    {
        if ((string) $run->origin_node_id === $nodeId) return match ((string) $run->status) { 'CREATED' => ['LOAD'], 'LOADED' => ['DEPART'], default => [] };
        if ((string) $run->destination_node_id === $nodeId) return match ((string) $run->status) { 'DEPARTED' => ['ARRIVE'], 'ARRIVED' => (string) $run->leg_status === 'RECEIVED' ? ['CLOSE'] : [], default => [] };
        return [];
    }

    private function assertLegOrder(object $leg): void
    {
        if ((int) $leg->leg_order > 1 && ! DB::table('route_plan_legs')->where(['route_plan_id' => $leg->route_plan_id, 'leg_order' => (int) $leg->leg_order - 1, 'status' => 'RECEIVED'])->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The preceding Route Leg has not been received.');
        if (DB::table('route_plan_legs')->where('route_plan_id', $leg->route_plan_id)->where('leg_order', '<', $leg->leg_order)->whereNot('status', 'RECEIVED')->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route Plan order cannot be skipped.');
    }

    private function assertPublishedConfiguration(object $leg): void
    {
        $available = $leg->source_route_definition_version_leg_id !== null && DB::table('route_definition_version_legs as source')->join('route_definition_versions as version', 'version.route_definition_version_id', '=', 'source.route_definition_version_id')->where(['source.route_definition_version_leg_id' => $leg->source_route_definition_version_leg_id, 'source.hq_id' => $leg->hq_id, 'version.status' => 'PUBLISHED'])->exists();
        if (! $available) throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The published Route configuration required by this Leg is unavailable.');
    }

    private function assertLinehaulNode(string $hqId, string $nodeId): void
    {
        $node = DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->first();
        $capabilities = $node === null ? [] : json_decode((string) ($node->capabilities ?? '[]'), true);
        if ($node === null || ! in_array('LINEHAUL', is_array($capabilities) ? $capabilities : [], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The origin Node is not active or capable of linehaul operations.');
    }

    private function assertDriver(string $hqId, string $nodeId, string $driverId, string $availability): void
    {
        $driver = DB::table('drivers')->where(['hq_id' => $hqId, 'driver_id' => $driverId])->lockForUpdate()->first();
        $capable = $driver !== null && DB::table('driver_capabilities')->where(['hq_id' => $hqId, 'driver_id' => $driverId, 'capability' => 'LINEHAUL'])->exists();
        if ($driver === null || (string) $driver->home_node_id !== $nodeId || (string) $driver->status !== 'ACTIVE' || (string) $driver->availability_status !== $availability || ! $capable) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Driver must belong to this HQ and Home Node and be active, available, and LINEHAUL-capable.');
    }

    private function assertVehicle(string $hqId, string $nodeId, string $vehicleId, string $availability, string $planId): void
    {
        $vehicle = DB::table('vehicles')->where(['hq_id' => $hqId, 'vehicle_id' => $vehicleId])->lockForUpdate()->first();
        if ($vehicle === null || (string) $vehicle->home_node_id !== $nodeId || (string) $vehicle->status !== 'ACTIVE' || (string) $vehicle->availability_status !== $availability) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Vehicle must belong to this HQ and Home Node and be active and operationally available.');
        $consignmentId = DB::table('route_plans')->where(['hq_id' => $hqId, 'route_plan_id' => $planId])->value('consignment_id');
        $parcels = DB::table('parcels')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->get();
        $weight = (int) round($parcels->sum(fn ($parcel): float => (float) ($parcel->weight_kg ?? 0) * 1000));
        $volume = (int) round($parcels->sum(fn ($parcel): float => (float) ($parcel->width_cm ?? 0) * (float) ($parcel->length_cm ?? 0) * (float) ($parcel->height_cm ?? 0)));
        if ($vehicle->capacity_weight_grams !== null && $weight > (int) $vehicle->capacity_weight_grams) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Vehicle weight capability is insufficient for the planned load.');
        if ($vehicle->capacity_volume_cm3 !== null && $volume > (int) $vehicle->capacity_volume_cm3) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Vehicle volume capability is insufficient for the planned load.');
    }

    private function lockedAtOrigin(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = DB::table('transport_runs')->where(['hq_id' => $actor->hqId, 'transport_run_id' => $id, 'origin_node_id' => $nodeId])->lockForUpdate()->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }

    private function lockedAtDestination(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = DB::table('transport_runs')->where(['hq_id' => $actor->hqId, 'transport_run_id' => $id, 'destination_node_id' => $nodeId])->lockForUpdate()->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }

    private function expected(object $row, int $expected): void
    {
        if ((int) $row->version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Transport Run version is stale.', details: ['current_version' => (int) $row->version]);
    }

    private function consignmentForLeg(string $legId): string
    {
        return (string) DB::table('route_plan_legs as leg')->join('route_plans as plan', 'plan.route_plan_id', '=', 'leg.route_plan_id')->where('leg.route_plan_leg_id', $legId)->value('plan.consignment_id');
    }

    /** @param array<string,mixed>|null $metadata */
    private function history(AuthenticatedPrincipal $actor, string $runId, int $sequence, string $event, ?string $from, string $to, string $nodeId, ?array $metadata = null): void
    {
        DB::table('transport_run_history')->insert(['transport_run_history_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'transport_run_id' => $runId, 'event_sequence' => $sequence, 'event_type' => $event, 'from_status' => $from, 'to_status' => $to, 'node_id' => $nodeId, 'actor_id' => $actor->userId, 'aggregate_version' => $sequence, 'metadata' => $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR), 'occurred_at' => now()]);
    }

    private function record(AuthenticatedPrincipal $actor, string $command, string $id, string $consignmentId, string $status, string $correlationId): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $command, 'TRANSPORT_RUN', $id, $correlationId, after: ['status' => $status], sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'TRANSPORT_RUN', $id, 'operations.command.executed', $correlationId, ['command' => $command, 'resource_id' => $id, 'consignment_id' => $consignmentId, 'status' => $status]);
    }

    private function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($entry) => $entry['module_code'] === 'LiveOperations' && $entry['status'] === 'ENABLED')) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        if (! in_array($nodeId, $context['accessible_node_ids'], true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
    }
}
