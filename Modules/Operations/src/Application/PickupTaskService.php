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

final readonly class PickupTaskService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private ParcelLifecycleService $lifecycle,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
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
        $id = $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $correlationId): string {
            $consignment = DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $nodeId, 'current_status' => 'CFM'])->first();
            if ($consignment === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a confirmed Consignment at its pickup node can create a Pickup Task.');
            $existing = DB::table('pickup_tasks')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->first();
            if ($existing !== null) return (string) $existing->pickup_task_id;
            $id = (string) Str::uuid();
            DB::table('pickup_tasks')->insert(['pickup_task_id' => $id, 'hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId, 'status' => 'PENDING', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->record($actor, 'PICKUP_TASK_CREATED', $id, $consignmentId, 'PENDING', $correlationId);
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
            DB::table('pickup_tasks')->where('pickup_task_id', $id)->update(['assigned_driver_id' => $driverId, 'status' => 'ASSIGNED', 'version' => $expected + 1, 'assigned_at' => now(), 'updated_at' => now()]);
            DB::table('consignments')->where('consignment_id', $task->consignment_id)->update(['pickup_man_id' => $driverId]);
            $this->record($actor, 'PICKUP_TASK_ASSIGNED', $id, (string) $task->consignment_id, 'ASSIGNED', $correlationId);
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
            $consignment=DB::table('consignments')->where(['hq_id'=>$actor->hqId,'consignment_id'=>$task->consignment_id])->lockForUpdate()->first();
            $snapshot=json_decode($consignment->commitment_snapshot??'null',true);
            $completedAt=now()->toISOString();
            $resolution=$snapshot ? (new \Modules\ServiceCatalog\Application\FrozenCommitmentCompletion())->pickupCompleted($snapshot,$completedAt) : null;
            if($resolution) {
                $end=$resolution['ends_at']??$resolution['computed_at']??null;
                $start=$resolution['starts_at']??$resolution['computed_at']??null;
                DB::table('consignments')->where(['hq_id'=>$actor->hqId,'consignment_id'=>$task->consignment_id])->update([
                    'delivery_commitment_at'=>$end ? \Carbon\CarbonImmutable::parse($end)->utc()->format('Y-m-d H:i:s.u') : null,
                    'delivery_commitment_end_at'=>$end ? \Carbon\CarbonImmutable::parse($end)->utc()->format('Y-m-d H:i:s.u') : null,
                    'delivery_commitment_start_at'=>$start ? \Carbon\CarbonImmutable::parse($start)->utc()->format('Y-m-d H:i:s.u') : null,
                    'delivery_commitment_resolution'=>json_encode(['pickup_completed_at'=>$completedAt,'result'=>$resolution],JSON_THROW_ON_ERROR),
                ]);
                $this->audit->write($actor->hqId,$actor->userId,'CONSIGNMENT_COMMITMENT_RESOLVED','CONSIGNMENT',(string)$task->consignment_id,$correlationId,after:['pickup_completed_at'=>$completedAt,'delivery'=>$resolution],sourceClient:'BRANCH_PANEL');
            }
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'PD', 'PU', 'PICKUP_COMPLETED', null, 'PICKUP_DRIVER', (string) $task->assigned_driver_id, $correlationId, (string) $task->assigned_driver_id);
            DB::table('pickup_tasks')->where('pickup_task_id', $id)->update(['status' => 'COMPLETED', 'version' => $expected + 1, 'completed_at' => now(), 'updated_at' => now()]);
            $this->record($actor, 'PICKUP_TASK_COMPLETED', $id, (string) $task->consignment_id, 'COMPLETED', $correlationId);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function fail(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $reasonCode, string $reason, string $correlationId): array
    {
        $this->accessExecution($actor, $nodeId, $id);
        throw new ApiException(
            ApiErrorCode::ExceptionReviewRequired,
            422,
            'Pickup failure must be submitted through an NPU Manifest Exception Review.',
        );
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
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($entry) => $entry['module_code'] === 'Pickup' && $entry['status'] === 'ENABLED')) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($nodeId, $context['accessible_node_ids'], true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        $task = DB::table('pickup_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'pickup_task_id' => $taskId])->first();
        if ($task === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $driverUser = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $task->assigned_driver_id])->value('user_id');
        if ((string) $driverUser === $actor->userId) return;
        if (! in_array('live_operations.intervene', $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
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
    private function item(object $row): array
    {
        $consignment = DB::table('consignments')->where(['hq_id' => $row->hq_id, 'consignment_id' => $row->consignment_id])->first();
        $node = DB::table('nodes')->where(['hq_id' => $row->hq_id, 'node_id' => $row->node_id])->first();
        $driver = $row->assigned_driver_id === null ? null : DB::table('drivers')->where(['hq_id' => $row->hq_id, 'driver_id' => $row->assigned_driver_id])->first();
        return [
            'pickup_task_id' => (string) $row->pickup_task_id,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => $consignment === null ? null : (string) $consignment->consignment_number,
            'sender_name' => $consignment === null ? null : (string) $consignment->sender_contact_name,
            'sender_mobile' => $consignment === null ? null : (string) $consignment->sender_mobile,
            'pickup_address' => $consignment === null ? null : (string) $consignment->sender_address_text,
            'pickup_commitment_at' => $consignment?->pickup_commitment_at,
            'pickup_window_code' => $consignment?->pickup_window_code,
            'node' => $node === null ? null : ['node_id' => (string) $node->node_id, 'node_code' => (string) $node->node_code, 'node_title' => (string) $node->node_title],
            'assigned_driver' => $driver === null ? null : ['driver_id' => (string) $driver->driver_id, 'driver_code' => (string) $driver->driver_code, 'display_name' => (string) $driver->display_name],
            'status' => (string) $row->status,
            'failure_reason_code' => $row->failure_reason_code,
            'failure_reason' => $row->failure_reason,
            'version' => (int) $row->version,
            'assigned_at' => $row->assigned_at,
            'started_at' => $row->started_at,
            'completed_at' => $row->completed_at,
            'failed_at' => $row->failed_at,
            'created_at' => $row->created_at,
        ];
    }

    private function record(AuthenticatedPrincipal $actor, string $command, string $id, string $consignmentId, string $status, string $correlationId): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $command, 'PICKUP_TASK', $id, $correlationId, after: ['status' => $status], sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'PICKUP_TASK', $id, 'operations.command.executed', $correlationId, [
            'command' => $command,
            'resource_id' => $id,
            'consignment_id' => $consignmentId,
            'status' => $status,
        ]);
    }
}
