<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\RecordTaskAction;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\CrmTask\Application\Contracts\TaskValidatorInterface;
use Modules\CrmTask\Application\Dto\TaskActionDto;
use Modules\CrmTask\Application\Repositories\ActivityRepositoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Recording what happened on a task, and what that does to the task, as one write. The interaction is
 * the evidence; the change beside it is optional, so an action may simply be logged.
 */
final readonly class RecordTaskActionHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private TaskAccessGuardInterface $accessGuard,
        private TaskRepositoryInterface $taskRepository,
        private ActivityRepositoryInterface $activityRepository,
        private TaskValidatorInterface $taskValidator,
    ) {}

    public function handle(RecordTaskActionCommand $command): RecordTaskActionResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);
        $action = $command->action;

        return $this->connection->transaction(function () use ($hqId, $command, $action): RecordTaskActionResult {
            $current = $this->taskRepository->lockForTenant($hqId, $command->taskId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $this->taskValidator->validateAction($hqId, $current, $action);

            $at = $this->clock->now();
            $draft = $action->activity;
            $activity = $this->activityRepository->create([
                'hq_id' => $hqId,
                'type' => $draft->type->value,
                'occurred_at' => $draft->occurredAt,
                'task_id' => $command->taskId,
                // The interaction inherits where the task is filed; it is never filed somewhere else.
                'customer_id' => $current->customer_id,
                'opportunity_id' => $current->opportunity_id,
                'body' => $draft->body,
                'result' => $draft->result,
                'contact_customer_id' => $draft->contactCustomerId,
                'direction' => $draft->direction?->value,
                'contact_value' => $draft->contactValue,
                'channel' => $draft->channel?->value,
                'duration_minutes' => $draft->durationMinutes,
                'call_outcome' => $draft->callOutcome?->value,
                'meeting_mode' => $draft->meetingMode?->value,
                'location' => $draft->location,
                'meeting_url' => $draft->meetingUrl,
                'document_version_id' => $draft->documentVersionId,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $this->applyToTask($hqId, $command->taskId, $action, $at);

            return new RecordTaskActionResult(
                $activity,
                $this->taskRepository->findForTenant($hqId, $command->taskId) ?? $current,
            );
        }, attempts: 3);
    }

    private function applyToTask(string $hqId, string $taskId, TaskActionDto $action, DateTimeImmutable $at): void
    {
        $changes = $action->task;
        $attributes = [];
        if ($changes->dueSpecified) {
            $attributes['due_at'] = $changes->dueAt;
        }
        if ($changes->remindSpecified) {
            $attributes['remind_at'] = $changes->remindAt;
        }
        if ($changes->status !== null) {
            $attributes['status'] = $changes->status->value;
            if ($changes->status === TaskStatus::COMPLETED) {
                // The server owns the completion time, and a closed task carries no live reminder.
                $attributes['completed_at'] = $at;
                $attributes['completion_result'] = $changes->completionResult;
                $attributes['remind_at'] = null;
            }
            if ($changes->status === TaskStatus::CANCELLED) {
                $attributes['remind_at'] = null;
            }
        }
        if ($attributes !== []) {
            $this->taskRepository->update($hqId, $taskId, $attributes);
        }
    }
}
