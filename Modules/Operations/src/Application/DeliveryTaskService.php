<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class DeliveryTaskService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private ParcelLifecycleService $lifecycle,
        private CoveragePolicyService $coverage,
        private RouteDefinitionService $routes,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    public function list(AuthenticatedPrincipal $actor, string $nodeId, array $filters = []): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $query = DB::table('delivery_tasks as task')->join('consignments as consignment', 'consignment.consignment_id', '=', 'task.consignment_id')->where(['task.hq_id' => $actor->hqId, 'task.node_id' => $nodeId]);
        if (($filters['status'] ?? '') !== '') $query->where('task.status', $filters['status']);
        if (($filters['search'] ?? '') !== '') {
            $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn ($search) => $search->where('consignment.consignment_number', 'like', $term)->orWhere('consignment.receiver_contact_name', 'like', $term)->orWhere('consignment.receiver_mobile', 'like', $term));
        }
        return $query->orderByDesc('task.created_at')->get(['task.*', 'consignment.consignment_number', 'consignment.receiver_contact_name', 'consignment.receiver_mobile', 'consignment.receiver_address_text'])->map(fn ($row): array => $this->summary($row))->all();
    }

    /** Called by destination reception inside the Manifest-owned transaction. */
    public function ensurePending(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId): string
    {
        $existing = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
        if ($existing !== null) {
            if ((string) $existing->node_id !== $nodeId) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Delivery Task belongs to another resolved Last-mile Node.');
            return (string) $existing->delivery_task_id;
        }
        $consignment = DB::table('consignments')->where([
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
        ])->lockForUpdate()->first();
        if ($consignment === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');

        $evidence = DB::table('last_mile_resolution_evidence')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->first();
        if ($evidence === null && $consignment->delivery_node_id === null
            && $this->nodeHasCapability((string) $actor->hqId, $nodeId, 'GATEWAY')) {
            $this->resolveLastMile($actor, $nodeId, $consignment);
            $evidence = DB::table('last_mile_resolution_evidence')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->first();
            if ((string) $evidence->last_mile_node_id !== $nodeId) return '';
        }
        if ($evidence !== null && (string) $evidence->last_mile_node_id !== $nodeId) return '';
        if ($consignment->delivery_node_id !== null && (string) $consignment->delivery_node_id !== $nodeId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Delivery Tasks can be created only at the resolved Last-mile Node.');
        }
        $id = (string) Str::uuid();
        DB::table('delivery_tasks')->insert(['delivery_task_id' => $id, 'hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId, 'last_mile_resolution_id' => $evidence?->last_mile_resolution_id, 'status' => 'PENDING', 'attempt_number' => 1, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->history($actor, $id, $consignmentId, 'CREATED', null, 'PENDING', 1);
        $this->record($actor, 'DELIVERY_TASK_CREATED', $id, $consignmentId, 'PENDING', $this->requestCorrelation());
        return $id;
    }

    /** Called by Manifest confirmation inside its existing transaction. */
    public function activateFromManifest(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $driverId, string $manifestId): string
    {
        $task = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId])->lockForUpdate()->first();
        if ($task === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'A pending Delivery Task is required before delivery activation.');
        if ((string) $task->status === 'IN_PROGRESS'
            && (string) $task->assigned_driver_id === $driverId
            && (string) $task->manifest_id === $manifestId) {
            return (string) $task->delivery_task_id;
        }
        if (! in_array((string) $task->status, ['PENDING', 'ASSIGNED'], true) || ($task->assigned_driver_id !== null && (string) $task->assigned_driver_id !== $driverId)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Delivery Task conflicts with the Manifest assignment.');
        $this->eligibleDriverForManifest($actor, $nodeId, $driverId, $manifestId, (string) $task->delivery_task_id);
        $version = (int) $task->version + 1;
        DB::table('delivery_tasks')->where('delivery_task_id', $task->delivery_task_id)->update(['assigned_driver_id' => $driverId, 'manifest_id' => $manifestId, 'status' => 'IN_PROGRESS', 'version' => $version, 'updated_at' => now()]);
        DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId, 'availability_status' => 'AVAILABLE'])->update(['availability_status' => 'ON_MISSION', 'updated_at' => now()]);
        DB::table('consignments')->where('consignment_id', $consignmentId)->update(['delivery_man_id' => $driverId]);
        $this->history($actor, (string) $task->delivery_task_id, $consignmentId, 'ACTIVATED', (string) $task->status, 'IN_PROGRESS', (int) $task->attempt_number, $driverId, metadata: ['manifest_id' => $manifestId]);
        return (string) $task->delivery_task_id;
    }

    /** @return array<string,mixed> */
    public function assign(AuthenticatedPrincipal $actor, string $nodeId, string $id, string $driverId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $driverId, $expected, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if (! in_array((string) $task->status, ['PENDING', 'ASSIGNED'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a pending or not-yet-activated Delivery Task can be assigned.');
            if ((string) $task->status === 'ASSIGNED' && (string) $task->assigned_driver_id === $driverId) return;
            $this->eligibleDriver($actor, $nodeId, $driverId, $id);
            $event = $task->assigned_driver_id === null ? 'ASSIGNED' : 'REASSIGNED';
            DB::table('delivery_tasks')->where(['delivery_task_id' => $id, 'version' => $expected])->update(['assigned_driver_id' => $driverId, 'status' => 'ASSIGNED', 'version' => $expected + 1, 'updated_at' => now()]);
            DB::table('consignments')->where('consignment_id', $task->consignment_id)->update(['delivery_man_id' => $driverId]);
            $this->history($actor, $id, (string) $task->consignment_id, $event, (string) $task->status, 'ASSIGNED', (int) $task->attempt_number, $driverId, metadata: ['previous_driver_id' => $task->assigned_driver_id]);
            $this->record($actor, 'DELIVERY_TASK_'.$event, $id, (string) $task->consignment_id, 'ASSIGNED', $correlationId);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function complete(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $recipientName, string $deliveredAt, ?string $note, string $correlationId): array
    {
        $this->executionAccess($actor, $nodeId, $id);
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $recipientName, $deliveredAt, $note, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if ((string) $task->status !== 'IN_PROGRESS') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Delivery completion is allowed only after the approved Delivery Manifest activates the Task.');
            $delivered = CarbonImmutable::parse($deliveredAt)->utc();
            if ($delivered->isFuture()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Delivery time cannot be in the future.');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'OD', 'OK', 'DELIVERY_COMPLETED', null, 'RECIPIENT', null, $correlationId, (string) $task->assigned_driver_id, (string) $task->manifest_id, safeNote: $note);
            DB::table('delivery_tasks')->where(['delivery_task_id' => $id, 'version' => $expected])->update(['status' => 'COMPLETED', 'recipient_name' => trim($recipientName), 'proof_type' => 'MANUAL_CONFIRMATION', 'proof_note' => $note, 'delivered_at' => $delivered->format('Y-m-d H:i:s.u'), 'version' => $expected + 1, 'updated_at' => now()]);
            DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $task->assigned_driver_id, 'availability_status' => 'ON_MISSION'])->update(['availability_status' => 'AVAILABLE', 'updated_at' => now()]);
            $this->history($actor, $id, (string) $task->consignment_id, 'COMPLETED', 'IN_PROGRESS', 'COMPLETED', (int) $task->attempt_number, (string) $task->assigned_driver_id, safeNote: $note, metadata: ['recipient_name' => trim($recipientName), 'proof_type' => 'MANUAL_CONFIRMATION', 'delivered_at' => $delivered->toISOString()]);
            $this->record($actor, 'DELIVERY_TASK_COMPLETED', $id, (string) $task->consignment_id, 'COMPLETED', $correlationId);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function fail(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $reasonCode, string $reason, string $correlationId): array
    {
        $this->executionAccess($actor, $nodeId, $id);
        throw new ApiException(
            ApiErrorCode::ExceptionReviewRequired,
            422,
            'Delivery failure must be submitted through a NOK Manifest Exception Review.',
        );
    }

    /** @return array<string,mixed> */
    public function retry(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $reason, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $reason, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if ((string) $task->status !== 'FAILED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a failed Delivery Task can be retried.');
            $case = DB::table('operational_exception_cases')->where(['hq_id' => $actor->hqId, 'delivery_task_id' => $id, 'exception_type' => 'NOK'])->orderByDesc('created_at')->lockForUpdate()->first();
            if ($case === null || (string) $case->case_status !== 'APPROVED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'The NOK case does not permit retry.');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'NOK', 'IR', 'DELIVERY_RETRY_REQUESTED', $nodeId, 'NODE', $nodeId, $correlationId, reasonCode: 'DELIVERY_RETRY', safeNote: $reason);
            $attempt = (int) $task->attempt_number + 1;
            DB::table('delivery_tasks')->where(['delivery_task_id' => $id, 'version' => $expected])->update(['assigned_driver_id' => null, 'manifest_id' => null, 'status' => 'PENDING', 'attempt_number' => $attempt, 'failure_reason_code' => null, 'failure_reason' => null, 'version' => $expected + 1, 'updated_at' => now()]);
            DB::table('operational_exception_cases')->where('exception_case_id', $case->exception_case_id)->update(['resolution_action' => 'RETRY', 'decision_note' => $reason, 'version' => (int) $case->version + 1, 'updated_at' => now()]);
            DB::table('operational_exception_history')->insert(['exception_history_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'exception_case_id' => $case->exception_case_id, 'action' => 'RETRY_REQUESTED', 'actor_id' => $actor->userId, 'safe_note' => $reason, 'created_at' => now()]);
            $this->history($actor, $id, (string) $task->consignment_id, 'RETRY_REQUESTED', 'FAILED', 'PENDING', $attempt, reasonCode: 'DELIVERY_RETRY', safeNote: $reason, metadata: ['exception_case_id' => $case->exception_case_id]);
            $this->record($actor, 'DELIVERY_TASK_RETRY_REQUESTED', $id, (string) $task->consignment_id, 'PENDING', $correlationId);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $row = DB::table('delivery_tasks as task')->join('consignments as consignment', 'consignment.consignment_id', '=', 'task.consignment_id')->join('nodes as node', 'node.node_id', '=', 'task.node_id')->leftJoin('drivers as driver', 'driver.driver_id', '=', 'task.assigned_driver_id')->where(['task.hq_id' => $actor->hqId, 'task.node_id' => $nodeId, 'task.delivery_task_id' => $id])->first(['task.*', 'consignment.consignment_number', 'consignment.receiver_contact_name', 'consignment.receiver_mobile', 'consignment.receiver_address_text', 'node.node_code', 'node.node_title', 'driver.driver_code', 'driver.display_name as driver_name']);
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $detail = $this->summary($row);
        $detail['node'] = ['node_id' => (string) $row->node_id, 'node_code' => (string) $row->node_code, 'node_title' => (string) $row->node_title];
        $detail['driver'] = $row->assigned_driver_id === null ? null : ['driver_id' => (string) $row->assigned_driver_id, 'driver_code' => (string) $row->driver_code, 'display_name' => (string) $row->driver_name];
        $detail['parcels'] = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'consignment_id' => $row->consignment_id])->orderBy('parcel_number')->get()->map(fn ($parcel): array => ['parcel_id' => (string) $parcel->parcel_id, 'parcel_number' => (string) $parcel->parcel_number, 'current_status' => (string) $parcel->current_status, 'current_node_id' => $parcel->current_node_id, 'current_custody_type' => (string) $parcel->current_custody_type, 'current_custodian_id' => $parcel->current_custodian_id])->all();
        $detail['history'] = DB::table('delivery_task_history')->where('delivery_task_id', $id)->orderBy('event_sequence')->get()->map(fn ($event): array => ['event_type' => (string) $event->event_type, 'from_status' => $event->from_status, 'to_status' => (string) $event->to_status, 'attempt_number' => (int) $event->attempt_number, 'assigned_driver_id' => $event->assigned_driver_id, 'reason_code' => $event->reason_code, 'safe_note' => $event->safe_note, 'metadata' => $event->metadata ? json_decode((string) $event->metadata, true, flags: JSON_THROW_ON_ERROR) : null, 'occurred_at' => $event->occurred_at])->all();
        $detail['last_mile_resolution'] = $this->resolution((string) $row->consignment_id);
        $detail['route_progress'] = $this->routeProgress((string) $row->consignment_id);
        $detail['permitted_actions'] = match ((string) $row->status) { 'PENDING' => ['ASSIGN'], 'ASSIGNED' => ['REASSIGN'], 'IN_PROGRESS' => ['COMPLETE', 'FAIL'], 'FAILED' => ['RETRY'], default => [] };
        return $detail;
    }

    private function resolveLastMile(AuthenticatedPrincipal $actor, string $gatewayNodeId, object $consignment): void
    {
        $city = $consignment->receiver_city_id === null ? null : DB::table('cities')->where('city_id', $consignment->receiver_city_id)->first();
        $input = array_filter(['province_id' => $city?->province_id, 'city_id' => $consignment->receiver_city_id, 'postal_code' => $consignment->receiver_postal_code, 'latitude' => $consignment->receiver_latitude === null ? null : (float) $consignment->receiver_latitude, 'longitude' => $consignment->receiver_longitude === null ? null : (float) $consignment->receiver_longitude], fn ($value) => $value !== null && $value !== '');
        $coverage = $this->coverage->resolve((string) $actor->hqId, 'LAST_MILE_NODE', $input, $consignment->service_offering_version_id);
        $lastMileNodeId = (string) $coverage['target_node_id'];
        if (! $this->nodeHasCapability((string) $actor->hqId, $lastMileNodeId, 'DELIVERY')) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published coverage resolved a Node without DELIVERY capability.');
        $planId = (string) DB::table('parcels')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignment->consignment_id])->whereNotNull('active_route_plan_id')->value('active_route_plan_id');
        if ($planId === '') throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'An active Route Plan is required for Last-mile resolution.');
        $route = $lastMileNodeId === $gatewayNodeId ? null : $this->routes->resolve((string) $actor->hqId, 'LAST_MILE', $gatewayNodeId, $lastMileNodeId, $consignment->service_offering_version_id);
        $resolutionId = (string) Str::uuid();
        DB::table('last_mile_resolution_evidence')->insert(['last_mile_resolution_id' => $resolutionId, 'hq_id' => $actor->hqId, 'consignment_id' => $consignment->consignment_id, 'route_plan_id' => $planId, 'destination_gateway_node_id' => $gatewayNodeId, 'last_mile_node_id' => $lastMileNodeId, 'coverage_policy_id' => $coverage['coverage_policy_id'], 'coverage_policy_version_id' => $coverage['coverage_policy_version_id'], 'coverage_rule_id' => $coverage['coverage_rule_id'], 'route_definition_version_id' => $route['route_definition_version_id'] ?? null, 'resolution_input' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'resolved_by' => $actor->userId, 'resolved_at' => CarbonImmutable::parse($coverage['resolved_at'])->utc()->format('Y-m-d H:i:s.u')]);
        if ($route !== null) {
            $nextOrder = ((int) DB::table('route_plan_legs')->where('route_plan_id', $planId)->max('leg_order')) + 1;
            foreach ($route['legs'] as $index => $leg) DB::table('route_plan_legs')->insert(['route_plan_leg_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'route_plan_id' => $planId, 'source_route_definition_leg_id' => $leg['route_definition_leg_id'], 'source_route_definition_version_leg_id' => $leg['route_definition_leg_id'], 'leg_order' => $nextOrder + $index, 'origin_node_id' => $leg['origin_node_id'], 'destination_node_id' => $leg['destination_node_id'], 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('route_plans')->where('route_plan_id', $planId)->update(['status' => 'IN_PROGRESS', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
        }
        DB::table('consignments')->where('consignment_id', $consignment->consignment_id)->update(['delivery_node_id' => $lastMileNodeId, 'updated_at' => now()]);
        $correlationId = $this->requestCorrelation();
        $this->audit->write($actor->hqId, $actor->userId, 'LAST_MILE_NODE_RESOLVED', 'CONSIGNMENT', (string) $consignment->consignment_id, $correlationId, after: ['last_mile_resolution_id' => $resolutionId, 'destination_gateway_node_id' => $gatewayNodeId, 'last_mile_node_id' => $lastMileNodeId], sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'CONSIGNMENT', (string) $consignment->consignment_id, 'operations.command.executed', $correlationId, ['command' => 'LAST_MILE_NODE_RESOLVED', 'resource_id' => (string) $consignment->consignment_id, 'consignment_id' => (string) $consignment->consignment_id, 'status' => 'RESOLVED']);
    }

    private function eligibleDriver(AuthenticatedPrincipal $actor, string $nodeId, string $driverId, ?string $currentTaskId = null): void
    {
        $driver = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId])->lockForUpdate()->first();
        $capable = $driver !== null && DB::table('driver_capabilities')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId, 'capability' => 'DELIVERY'])->exists();
        if ($driver === null || (string) $driver->home_node_id !== $nodeId || (string) $driver->status !== 'ACTIVE' || (string) $driver->availability_status !== 'AVAILABLE' || ! $capable) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Driver must belong to this HQ and Home Node and be active, available, and DELIVERY-capable.');
        $assigned = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'assigned_driver_id' => $driverId])->whereIn('status', ['ASSIGNED', 'IN_PROGRESS']);
        if ($currentTaskId !== null) $assigned->where('delivery_task_id', '!=', $currentTaskId);
        if ($assigned->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Driver is already assigned to an active Delivery Task.');
    }

    private function eligibleDriverForManifest(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $driverId,
        string $manifestId,
        string $currentTaskId,
    ): void {
        $driver = DB::table('drivers')->where([
            'hq_id' => $actor->hqId,
            'driver_id' => $driverId,
        ])->lockForUpdate()->first();
        $capable = $driver !== null && DB::table('driver_capabilities')->where([
            'hq_id' => $actor->hqId,
            'driver_id' => $driverId,
            'capability' => 'DELIVERY',
        ])->exists();
        $sameManifestMission = $driver !== null
            && (string) $driver->availability_status === 'ON_MISSION'
            && DB::table('delivery_tasks')->where([
                'hq_id' => $actor->hqId,
                'assigned_driver_id' => $driverId,
                'manifest_id' => $manifestId,
                'status' => 'IN_PROGRESS',
            ])->exists();
        if ($driver === null || (string) $driver->home_node_id !== $nodeId
            || (string) $driver->status !== 'ACTIVE'
            || (! $sameManifestMission && (string) $driver->availability_status !== 'AVAILABLE')
            || ! $capable) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'The Delivery Driver is unavailable for this Manifest.');
        }
        $conflict = DB::table('delivery_tasks')->where([
            'hq_id' => $actor->hqId,
            'assigned_driver_id' => $driverId,
        ])->whereIn('status', ['ASSIGNED', 'IN_PROGRESS'])
            ->where('delivery_task_id', '!=', $currentTaskId)
            ->where(fn ($query) => $query->whereNull('manifest_id')->orWhere('manifest_id', '!=', $manifestId))
            ->exists();
        if ($conflict) {
            throw new ApiException(ApiErrorCode::DriverUnavailable, 422, 'The Delivery Driver has another active assignment.');
        }
    }

    private function nodeHasCapability(string $hqId, string $nodeId, string $capability): bool
    {
        $node = DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->first();
        if ($node === null) return false;
        $capabilities = json_decode((string) ($node->capabilities ?? '[]'), true);
        return in_array($capability, is_array($capabilities) ? $capabilities : [], true);
    }

    private function executionAccess(AuthenticatedPrincipal $actor, string $nodeId, string $id): void
    {
        $task = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'delivery_task_id' => $id])->first();
        if ($task === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $driverUser = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $task->assigned_driver_id])->value('user_id');
        if ((string) $driverUser === $actor->userId) return;
        $this->access($actor, $nodeId, 'live_operations.intervene');
    }

    private function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'delivery_task_id' => $id])->lockForUpdate()->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }

    private function version(object $task, int $expected): void
    {
        if ((int) $task->version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Delivery Task version is stale.', details: ['current_version' => (int) $task->version]);
    }

    /** @return array<string,mixed> */
    private function summary(object $row): array
    {
        return ['delivery_task_id' => (string) $row->delivery_task_id, 'consignment_id' => (string) $row->consignment_id, 'consignment_number' => (string) $row->consignment_number, 'node_id' => (string) $row->node_id, 'assigned_driver_id' => $row->assigned_driver_id, 'manifest_id' => $row->manifest_id, 'status' => (string) $row->status, 'attempt_number' => (int) $row->attempt_number, 'recipient' => ['name' => (string) $row->receiver_contact_name, 'mobile' => (string) $row->receiver_mobile, 'address' => (string) $row->receiver_address_text], 'recipient_name' => $row->recipient_name, 'proof_type' => $row->proof_type, 'proof_note' => $row->proof_note, 'failure_reason_code' => $row->failure_reason_code, 'failure_reason' => $row->failure_reason, 'version' => (int) $row->version, 'delivered_at' => $row->delivered_at];
    }

    /** @return array<string,mixed>|null */
    private function resolution(string $consignmentId): ?array
    {
        $row = DB::table('last_mile_resolution_evidence')->where('consignment_id', $consignmentId)->first();
        if ($row === null) return null;
        return ['last_mile_resolution_id' => (string) $row->last_mile_resolution_id, 'destination_gateway_node_id' => (string) $row->destination_gateway_node_id, 'last_mile_node_id' => (string) $row->last_mile_node_id, 'coverage_policy_id' => (string) $row->coverage_policy_id, 'coverage_policy_version_id' => (string) $row->coverage_policy_version_id, 'coverage_rule_id' => (string) $row->coverage_rule_id, 'route_definition_version_id' => $row->route_definition_version_id, 'resolution_input' => json_decode((string) $row->resolution_input, true, flags: JSON_THROW_ON_ERROR), 'resolved_at' => $row->resolved_at];
    }

    /** @return list<array<string,mixed>> */
    private function routeProgress(string $consignmentId): array
    {
        return DB::table('route_plan_legs as leg')->join('route_plans as plan', 'plan.route_plan_id', '=', 'leg.route_plan_id')->join('nodes as origin', 'origin.node_id', '=', 'leg.origin_node_id')->join('nodes as destination', 'destination.node_id', '=', 'leg.destination_node_id')->where('plan.consignment_id', $consignmentId)->orderBy('leg.leg_order')->get(['leg.*', 'origin.node_code as origin_code', 'origin.node_title as origin_title', 'destination.node_code as destination_code', 'destination.node_title as destination_title'])->map(fn ($leg): array => ['route_plan_leg_id' => (string) $leg->route_plan_leg_id, 'leg_order' => (int) $leg->leg_order, 'status' => (string) $leg->status, 'origin_node' => ['node_id' => (string) $leg->origin_node_id, 'node_code' => (string) $leg->origin_code, 'node_title' => (string) $leg->origin_title], 'destination_node' => ['node_id' => (string) $leg->destination_node_id, 'node_code' => (string) $leg->destination_code, 'node_title' => (string) $leg->destination_title], 'routed_at' => $leg->routed_at, 'received_at' => $leg->received_at])->all();
    }

    /** @param array<string,mixed>|null $metadata */
    private function history(AuthenticatedPrincipal $actor, string $taskId, string $consignmentId, string $event, ?string $from, string $to, int $attempt, ?string $driverId = null, ?string $reasonCode = null, ?string $safeNote = null, ?array $metadata = null): void
    {
        $sequence = ((int) DB::table('delivery_task_history')->where('delivery_task_id', $taskId)->max('event_sequence')) + 1;
        DB::table('delivery_task_history')->insert(['delivery_task_history_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'delivery_task_id' => $taskId, 'consignment_id' => $consignmentId, 'event_sequence' => $sequence, 'event_type' => $event, 'from_status' => $from, 'to_status' => $to, 'attempt_number' => $attempt, 'assigned_driver_id' => $driverId, 'actor_id' => $actor->userId, 'reason_code' => $reasonCode, 'safe_note' => $safeNote, 'metadata' => $metadata === null ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'occurred_at' => now()]);
    }

    private function record(AuthenticatedPrincipal $actor, string $command, string $id, string $consignmentId, string $status, string $correlationId): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $command, 'DELIVERY_TASK', $id, $correlationId, after: ['status' => $status], sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'DELIVERY_TASK', $id, 'operations.command.executed', $correlationId, ['command' => $command, 'resource_id' => $id, 'consignment_id' => $consignmentId, 'status' => $status]);
    }

    private function requestCorrelation(): string
    {
        $value = request()?->attributes->get('correlation_id');
        return is_string($value) && $value !== '' ? $value : (string) Str::uuid();
    }

    private function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($entry) => $entry['module_code'] === 'LiveOperations' && $entry['status'] === 'ENABLED')) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        if (! in_array($nodeId, \Modules\Foundation\Application\ScopedAccess::nodes($context, $permission), true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
    }
}
