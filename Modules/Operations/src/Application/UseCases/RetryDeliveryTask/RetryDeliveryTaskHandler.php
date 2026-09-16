<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\RetryDeliveryTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class RetryDeliveryTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\DeliveryAccessGuard $deliveryAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\DeliveryTaskReader $deliveryTaskReader,
        private \Modules\Operations\Application\Services\DeliveryAssignmentGuard $deliveryAssignmentGuard,
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Operations\Application\ParcelLifecycleService $lifecycle,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Operations\Application\Services\DeliveryTaskRecorder $deliveryTaskRecorder,
        private \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler $getDeliveryTask,
    )
    {
    }

    public function handle(RetryDeliveryTaskCommand $command): RetryDeliveryTaskResult
    {
        return new RetryDeliveryTaskResult($this->execute($command->actor, $command->nodeId, $command->id, $command->expected, $command->reason, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $reason,
        string $correlationId,
    ): array
    {
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $reason, $correlationId): void {
            $task = $this->deliveryTaskReader->locked($actor, $nodeId, $id);
            $this->deliveryAssignmentGuard->version($task, $expected);
            if ((string) $task->status !== 'FAILED') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a failed Delivery Task can be retried.');
            }
            $case = $this->tasks->lockLatestNokCase($actor->hqId, $id);
            if ($case === null || (string) $case->case_status !== 'APPROVED') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The NOK case does not permit retry.');
            }
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'NOK', 'IR', 'DELIVERY_RETRY_REQUESTED', $nodeId, 'NODE', $nodeId, $correlationId, reasonCode: 'DELIVERY_RETRY', safeNote: $reason);
            $attempt = (int) $task->attempt_number + 1;
            $this->tasks->updateVersion($id, $expected, [
                'assigned_driver_id' => null,
                'manifest_id' => null,
                'status' => 'PENDING',
                'attempt_number' => $attempt,
                'failure_reason_code' => null,
                'failure_reason' => null,
                'version' => $expected + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->tasks->updateExceptionCase($case->exception_case_id, [
                'resolution_action' => 'RETRY',
                'decision_note' => $reason,
                'version' => (int) $case->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->tasks->appendExceptionHistory([
                'exception_history_id' => $this->identifiers->uuid(),
                'hq_id' => $actor->hqId,
                'exception_case_id' => $case->exception_case_id,
                'action' => 'RETRY_REQUESTED',
                'actor_id' => $actor->userId,
                'safe_note' => $reason,
                'created_at' => $this->clock->now(),
            ]);
            $this->deliveryTaskRecorder->history($actor, $id, (string) $task->consignment_id, 'RETRY_REQUESTED', 'FAILED', 'PENDING', $attempt, reasonCode: 'DELIVERY_RETRY', safeNote: $reason, metadata: ['exception_case_id' => $case->exception_case_id]);
            $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_RETRY_REQUESTED', $id, (string) $task->consignment_id, 'PENDING', $correlationId);
        });
        return $this->getDeliveryTask->handle(new \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand($actor, $nodeId, $id))->data;
    }
}
