<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\RetryDeliveryTask;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Consignment\Domain\Enums\CustodyType;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ExceptionCaseStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DeliveryAccessGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryAssignmentGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskReaderInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskRecorderInterface;
use Modules\Operations\Application\Contracts\ParcelLifecycleServiceInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\OperationalExceptionRepositoryInterface;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionHistoryRecord;

final readonly class RetryDeliveryTaskHandler
{
    public function __construct(
        private DeliveryAccessGuardInterface $deliveryAccessGuard,
        private ConnectionInterface $connection,
        private DeliveryTaskReaderInterface $deliveryTaskReader,
        private DeliveryAssignmentGuardInterface $deliveryAssignmentGuard,
        private ParcelLifecycleServiceInterface $parcelLifecycleService,
        private ClockInterface $clock,
        private DeliveryTaskRecorderInterface $deliveryTaskRecorder,
        private GetDeliveryTaskHandler $getDeliveryTaskHandler,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
        private OperationalExceptionRepositoryInterface $operationalExceptionRepository,
    ) {}

    public function handle(RetryDeliveryTaskCommand $command): DeliveryTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $expected = $command->expected;
        $reason = $command->reason;
        $correlationId = $command->correlationId;
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $this->connection->transaction(function () use ($actor, $nodeId, $id, $expected, $reason, $correlationId): void {
            $task = $this->deliveryTaskReader->locked($actor, $nodeId, $id);
            $this->deliveryAssignmentGuard->version($task, $expected);
            if ($task->status->value !== 'FAILED') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_failed_delivery_task_can_be_retried');
            }
            $case = $this->operationalExceptionRepository->lockLatestForDeliveryTask($actor->hqId, $id, ConsignmentStatus::DeliveryFailed->value);
            if ($case === null || (string) $case->case_status !== ExceptionCaseStatus::Approved->value) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.nok_case_does_not_permit_retry');
            }
            $this->parcelLifecycleService->transition($actor, (string) $task->consignment_id, ConsignmentStatus::DeliveryFailed->value, ConsignmentStatus::InboundReceived->value, 'DELIVERY_RETRY_REQUESTED', $nodeId, CustodyType::Node->value, $nodeId, $correlationId, reasonCode: 'DELIVERY_RETRY', safeNote: $reason);
            $attempt = (int) $task->attempt_number + 1;
            $this->deliveryTaskRepository->updateExpectedVersion($id, $expected, [
                'assigned_driver_id' => null,
                'manifest_id' => null,
                'status' => 'PENDING',
                'attempt_number' => $attempt,
                'failure_reason_code' => null,
                'failure_reason' => null,
                'version' => $expected + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->operationalExceptionRepository->update((string) $case->exception_case_id, [
                'resolution_action' => 'RETRY',
                'decision_note' => $reason,
                'version' => (int) $case->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            (new OperationalExceptionHistoryRecord)->forceFill([

                'hq_id' => $actor->hqId,
                'exception_case_id' => $case->exception_case_id,
                'action' => 'RETRY_REQUESTED',
                'actor_id' => $actor->userId,
                'safe_note' => $reason,
                'created_at' => $this->clock->now(),
            ])->save();
            $this->deliveryTaskRecorder->history($actor, $id, (string) $task->consignment_id, 'RETRY_REQUESTED', 'FAILED', 'PENDING', $attempt, reasonCode: 'DELIVERY_RETRY', safeNote: $reason, metadata: ['exception_case_id' => $case->exception_case_id]);
            $this->deliveryTaskRecorder->record($actor, 'DELIVERY_TASK_RETRY_REQUESTED', $id, (string) $task->consignment_id, 'PENDING', $correlationId);
        }, attempts: 3);

        return $this->getDeliveryTaskHandler->handle(new GetDeliveryTaskCommand($actor, $nodeId, $id));
    }
}
