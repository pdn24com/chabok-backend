<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\AssignTask;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\CrmTask\Application\Contracts\TaskValidatorInterface;
use Modules\CrmTask\Application\Repositories\TaskAssignmentEventRepositoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class AssignTaskHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private TaskAccessGuardInterface $accessGuard,
        private TaskRepositoryInterface $taskRepository,
        private TaskAssignmentEventRepositoryInterface $taskAssignmentEventRepository,
        private TaskValidatorInterface $taskValidator,
    ) {}

    public function handle(AssignTaskCommand $command): AssignTaskResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);
        $assignment = $command->assignment;

        return $this->connection->transaction(function () use ($hqId, $command, $assignment): AssignTaskResult {
            $current = $this->taskRepository->lockForTenant($hqId, $command->taskId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }

            // The operation key is unique per task, so a resubmitted move replays instead of stacking.
            if ($assignment->operationKey !== null) {
                $replayed = $this->taskAssignmentEventRepository->findByOperationKey($hqId, $command->taskId, $assignment->operationKey);
                if ($replayed !== null) {
                    return new AssignTaskResult($replayed, $current);
                }
            }

            $this->taskValidator->validateAssignment($hqId, $current, $assignment, $command->actor->userId);

            $at = $this->clock->now();
            $event = $this->taskAssignmentEventRepository->create([
                'hq_id' => $hqId,
                'task_id' => $command->taskId,
                'event_type' => $assignment->eventType->value,
                'to_team_id' => $assignment->toTeamId,
                'from_user_id' => $current->assignee_id,
                'to_user_id' => $assignment->toUserId,
                'actor_id' => $command->actor->userId,
                'reason' => $assignment->reason,
                'occurred_at' => $at,
                'operation_key' => $assignment->operationKey ?? 'assign:'.$at->format('Uu'),
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            // A task without an owner is exactly that; the team on the event is history, not a queue.
            $attributes = ['assignee_id' => $assignment->toUserId];
            if ($assignment->dueSpecified) {
                $attributes['due_at'] = $assignment->dueAt;
            }
            if ($assignment->remindSpecified) {
                $attributes['remind_at'] = $assignment->remindAt;
            }
            // The database refuses a reminder with no one to remind, so an unowned task loses it.
            if ($assignment->toUserId === null) {
                $attributes['remind_at'] = null;
            }
            $this->taskRepository->update($hqId, $command->taskId, $attributes);

            return new AssignTaskResult($event, $this->taskRepository->findForTenant($hqId, $command->taskId) ?? $current);
        }, attempts: 3);
    }
}
