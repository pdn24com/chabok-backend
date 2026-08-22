<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PickupTaskService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private ParcelLifecycleService $lifecycle,
    ) {}

    /** @return list<array<string,mixed>> */
    public function list(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->access($actor, $nodeId, 'pickup_request.view');
        return DB::table('pickup_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId])->orderByDesc('created_at')->get()->map(fn ($row) => $this->item($row))->all();
    }

    /** @return array<string,mixed> */
    public function create(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'pickup_request.create');
        $id = $this->transactions->run(function () use ($actor, $nodeId, $consignmentId): string {
            $consignment = DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId, 'current_status' => 'CFM'])->first();
            if ($consignment === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a confirmed Consignment at its pickup node can create a Pickup Task.');
            $existing = DB::table('pickup_tasks')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->first();
            if ($existing !== null) return (string) $existing->pickup_task_id;
            $id = (string) Str::uuid();
            DB::table('pickup_tasks')->insert(['pickup_task_id' => $id, 'hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId, 'status' => 'PENDING', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            return $id;
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->access($actor, $nodeId, 'pickup_request.view');
        $row = DB::table('pickup_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'pickup_task_id' => $id])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $this->item($row);
    }

    /** @return array<string,mixed> */
    public function assign(AuthenticatedPrincipal $actor, string $nodeId, string $id, string $driverId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'pickup_request.assign');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $driverId, $expected, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if ($task->status !== 'PENDING') throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Pickup Task cannot be assigned in its current state.');
            $this->eligibleDriver($actor, $nodeId, $driverId, 'PICKUP');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'CFM', 'PD', 'PICKUP_ASSIGNED', null, 'PICKUP_DRIVER', $driverId, $correlationId, $driverId);
            DB::table('pickup_tasks')->where('pickup_task_id', $id)->update(['assigned_driver_id' => $driverId, 'status' => 'ASSIGNED', 'version' => $expected + 1, 'updated_at' => now()]);
            DB::table('consignments')->where('consignment_id', $task->consignment_id)->update(['pickup_man_id' => $driverId]);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function complete(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId): array
    {
        $this->accessExecution($actor, $nodeId, $id);
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if (! in_array($task->status, ['ASSIGNED', 'IN_PROGRESS'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Pickup Task cannot be completed in its current state.');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'PD', 'PU', 'PICKUP_COMPLETED', null, 'PICKUP_DRIVER', (string) $task->assigned_driver_id, $correlationId, (string) $task->assigned_driver_id);
            DB::table('pickup_tasks')->where('pickup_task_id', $id)->update(['status' => 'COMPLETED', 'version' => $expected + 1, 'completed_at' => now(), 'updated_at' => now()]);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function fail(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $reasonCode, string $reason, string $correlationId): array
    {
        $this->accessExecution($actor, $nodeId, $id);
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $reasonCode, $reason, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if (! in_array($task->status, ['ASSIGNED', 'IN_PROGRESS'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Pickup Task cannot fail in its current state.');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'PD', 'NPU', 'PICKUP_FAILED', null, 'PICKUP_DRIVER', (string) $task->assigned_driver_id, $correlationId, (string) $task->assigned_driver_id, reasonCode: $reasonCode, safeNote: $reason);
            DB::table('pickup_tasks')->where('pickup_task_id', $id)->update(['status' => 'FAILED', 'failure_reason_code' => $reasonCode, 'failure_reason' => $reason, 'version' => $expected + 1, 'completed_at' => now(), 'updated_at' => now()]);
            $this->exception($actor, (string) $task->consignment_id, $id, (string) $task->assigned_driver_id, 'NPU', $reasonCode, $reason);
        });
        return $this->get($actor, $nodeId, $id);
    }

    private function exception(AuthenticatedPrincipal $actor, string $consignmentId, string $taskId, string $driverId, string $type, string $code, string $reason): void
    {
        $caseId = (string) Str::uuid();
        DB::table('operational_exception_cases')->insert(['exception_case_id' => $caseId, 'hq_id' => $actor->hqId, 'exception_type' => $type, 'consignment_id' => $consignmentId, 'pickup_task_id' => $taskId, 'driver_id' => $driverId, 'submitted_by' => $actor->userId, 'reason_code' => $code, 'description' => $reason, 'case_status' => 'APPROVED', 'reviewed_by' => $actor->userId, 'reviewed_at' => now(), 'decision_note' => 'Operational failure command accepted.', 'resolution_action' => 'ESCALATE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('operational_exception_history')->insert(['exception_history_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'exception_case_id' => $caseId, 'action' => 'APPROVED_AND_APPLIED', 'actor_id' => $actor->userId, 'safe_note' => $reason, 'created_at' => now()]);
    }

    private function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = DB::table('pickup_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'pickup_task_id' => $id])->lockForUpdate()->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }
    private function version(object $task, int $expected): void { if ((int) $task->version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Pickup Task version is stale.', details: ['current_version' => (int) $task->version]); }
    private function eligibleDriver(AuthenticatedPrincipal $actor, string $nodeId, string $driverId, string $capability): void
    {
        $eligible = DB::table('drivers as d')->where(['d.hq_id' => $actor->hqId, 'd.driver_id' => $driverId, 'd.home_node_id' => $nodeId, 'd.status' => 'ACTIVE', 'd.availability_status' => 'AVAILABLE'])->whereExists(fn ($q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', $capability))->exists();
        if (! $eligible) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected driver is not active, available, capable, or in scope.');
    }
    private function accessExecution(AuthenticatedPrincipal $actor, string $nodeId, string $taskId): void
    {
        $task = DB::table('pickup_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'pickup_task_id' => $taskId])->first();
        if ($task === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $driverUser = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $task->assigned_driver_id])->value('user_id');
        if ((string) $driverUser === $actor->userId) return;
        $this->access($actor, $nodeId, 'live_operations.intervene');
    }
    private function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($e) => $e['module_code'] === 'Pickup' && $e['status'] === 'ENABLED')) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        if (! in_array($nodeId, $context['accessible_node_ids'], true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
    }
    /** @return array<string,mixed> */
    private function item(object $row): array { return ['pickup_task_id' => (string) $row->pickup_task_id, 'consignment_id' => (string) $row->consignment_id, 'node_id' => (string) $row->node_id, 'assigned_driver_id' => $row->assigned_driver_id, 'status' => (string) $row->status, 'failure_reason_code' => $row->failure_reason_code, 'failure_reason' => $row->failure_reason, 'version' => (int) $row->version, 'completed_at' => $row->completed_at]; }
}
