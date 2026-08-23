<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
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
    ) {}

    /** @return list<array<string,mixed>> */
    public function list(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        return DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId])->orderByDesc('created_at')->get()->map(fn ($row) => $this->item($row))->all();
    }

    /** Called by final-node inbound reception inside the Manifest transaction. */
    public function ensurePending(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId): string
    {
        $existing = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
        if ($existing !== null) return (string) $existing->delivery_task_id;
        $id = (string) Str::uuid();
        DB::table('delivery_tasks')->insert(['delivery_task_id' => $id, 'hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId, 'status' => 'PENDING', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    /** @return array<string,mixed> */
    public function assign(AuthenticatedPrincipal $actor, string $nodeId, string $id, string $driverId, int $expected, string $correlationId): array
    {
        $this->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $driverId, $expected): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if ($task->status !== 'PENDING') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a pending Delivery Task can be assigned.');
            $this->eligibleDriver($actor, $nodeId, $driverId);
            DB::table('delivery_tasks')->where('delivery_task_id', $id)->update(['assigned_driver_id' => $driverId, 'status' => 'ASSIGNED', 'version' => $expected + 1, 'updated_at' => now()]);
            DB::table('consignments')->where('consignment_id', $task->consignment_id)->update(['delivery_man_id' => $driverId]);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** Called by Manifest confirmation inside its existing transaction. */
    public function activateFromManifest(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $driverId, string $manifestId): string
    {
        $this->eligibleDriver($actor, $nodeId, $driverId);
        $existing = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
        if ($existing !== null) {
            if (($existing->assigned_driver_id !== null && (string) $existing->assigned_driver_id !== $driverId) || ! in_array($existing->status, ['PENDING', 'ASSIGNED'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Delivery Task conflicts with the Manifest assignment.');
            }
            DB::table('delivery_tasks')->where('delivery_task_id', $existing->delivery_task_id)->update(['assigned_driver_id' => $driverId, 'manifest_id' => $manifestId, 'status' => 'ASSIGNED', 'updated_at' => now()]);
            return (string) $existing->delivery_task_id;
        }
        $id = (string) Str::uuid();
        DB::table('delivery_tasks')->insert(['delivery_task_id' => $id, 'hq_id' => $actor->hqId, 'consignment_id' => $consignmentId, 'node_id' => $nodeId, 'assigned_driver_id' => $driverId, 'manifest_id' => $manifestId, 'status' => 'ASSIGNED', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('consignments')->where('consignment_id', $consignmentId)->update(['delivery_man_id' => $driverId]);
        return $id;
    }

    /** @return array<string,mixed> */
    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->access($actor, $nodeId, 'live_operations.view');
        $row = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'delivery_task_id' => $id])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $this->item($row);
    }

    /** @return array<string,mixed> */
    public function complete(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $recipientName, string $deliveredAt, ?string $note, string $correlationId): array
    {
        $this->executionAccess($actor, $nodeId, $id);
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $recipientName, $deliveredAt, $note, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if (! in_array($task->status, ['ASSIGNED', 'IN_PROGRESS'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Delivery Task cannot be completed in its current state.');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'OD', 'OK', 'DELIVERY_COMPLETED', null, 'RECIPIENT', null, $correlationId, (string) $task->assigned_driver_id, (string) $task->manifest_id, safeNote: $note);
            DB::table('delivery_tasks')->where('delivery_task_id', $id)->update(['status' => 'COMPLETED', 'recipient_name' => $recipientName, 'proof_type' => 'MANUAL_CONFIRMATION', 'proof_note' => $note, 'delivered_at' => CarbonImmutable::parse($deliveredAt)->utc()->format('Y-m-d H:i:s.u'), 'version' => $expected + 1, 'updated_at' => now()]);
        });
        return $this->get($actor, $nodeId, $id);
    }

    /** @return array<string,mixed> */
    public function fail(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $reasonCode, string $reason, string $correlationId): array
    {
        $this->executionAccess($actor, $nodeId, $id);
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $reasonCode, $reason, $correlationId): void {
            $task = $this->locked($actor, $nodeId, $id); $this->version($task, $expected);
            if (! in_array($task->status, ['ASSIGNED', 'IN_PROGRESS'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Delivery Task cannot fail in its current state.');
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'OD', 'NOK', 'DELIVERY_FAILED', null, 'DELIVERY_DRIVER', (string) $task->assigned_driver_id, $correlationId, (string) $task->assigned_driver_id, (string) $task->manifest_id, $reasonCode, $reason);
            DB::table('delivery_tasks')->where('delivery_task_id', $id)->update(['status' => 'FAILED', 'failure_reason_code' => $reasonCode, 'failure_reason' => $reason, 'version' => $expected + 1, 'updated_at' => now()]);
            $caseId = (string) Str::uuid();
            DB::table('operational_exception_cases')->insert(['exception_case_id' => $caseId, 'hq_id' => $actor->hqId, 'exception_type' => 'NOK', 'consignment_id' => $task->consignment_id, 'delivery_task_id' => $id, 'driver_id' => $task->assigned_driver_id, 'submitted_by' => $actor->userId, 'reason_code' => $reasonCode, 'description' => $reason, 'case_status' => 'APPROVED', 'reviewed_by' => $actor->userId, 'reviewed_at' => now(), 'decision_note' => 'Operational failure command accepted.', 'resolution_action' => 'ESCALATE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('operational_exception_history')->insert(['exception_history_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId, 'exception_case_id' => $caseId, 'action' => 'APPROVED_AND_APPLIED', 'actor_id' => $actor->userId, 'safe_note' => $reason, 'created_at' => now()]);
        });
        return $this->get($actor, $nodeId, $id);
    }

    private function executionAccess(AuthenticatedPrincipal $actor, string $nodeId, string $id): void
    {
        $task = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'delivery_task_id' => $id])->first();
        if ($task === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $driverUser = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $task->assigned_driver_id])->value('user_id');
        if ((string) $driverUser === $actor->userId) return;
        $this->access($actor, $nodeId, 'live_operations.intervene');
    }
    private function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($e) => $e['module_code'] === 'LiveOperations' && $e['status'] === 'ENABLED')) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        if (! in_array($nodeId, $context['accessible_node_ids'], true)) throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
    }
    private function eligibleDriver(AuthenticatedPrincipal $actor, string $nodeId, string $driverId): void
    {
        $eligible = DB::table('drivers as d')->where(['d.hq_id' => $actor->hqId, 'd.driver_id' => $driverId, 'd.home_node_id' => $nodeId, 'd.status' => 'ACTIVE', 'd.availability_status' => 'AVAILABLE'])->whereExists(fn ($q) => $q->selectRaw('1')->from('driver_capabilities as dc')->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', 'DELIVERY'))->exists();
        if (! $eligible) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected delivery driver is not active, available, capable, or in scope.');
    }
    private function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): object { $row = DB::table('delivery_tasks')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'delivery_task_id' => $id])->lockForUpdate()->first(); if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'); return $row; }
    private function version(object $task, int $expected): void { if ((int) $task->version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Delivery Task version is stale.', details: ['current_version' => (int) $task->version]); }
    /** @return array<string,mixed> */
    private function item(object $row): array { return ['delivery_task_id' => (string) $row->delivery_task_id, 'consignment_id' => (string) $row->consignment_id, 'node_id' => (string) $row->node_id, 'assigned_driver_id' => $row->assigned_driver_id, 'manifest_id' => $row->manifest_id, 'status' => (string) $row->status, 'recipient_name' => $row->recipient_name, 'proof_type' => $row->proof_type, 'proof_note' => $row->proof_note, 'failure_reason_code' => $row->failure_reason_code, 'failure_reason' => $row->failure_reason, 'version' => (int) $row->version, 'delivered_at' => $row->delivered_at]; }
}
