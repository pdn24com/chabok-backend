<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\CreateTask;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\CrmTask\Application\Contracts\TaskValidatorInterface;
use Modules\CrmTask\Application\Repositories\TaskAssignmentEventRepositoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Domain\Enums\TaskAssignmentEventType;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;

final readonly class CreateTaskHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private TaskAccessGuardInterface $accessGuard,
        private TaskRepositoryInterface $taskRepository,
        private TaskAssignmentEventRepositoryInterface $taskAssignmentEventRepository,
        private TaskValidatorInterface $taskValidator,
    ) {}

    public function handle(CreateTaskCommand $command): CreateTaskResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);
        $input = $command->input;

        return $this->connection->transaction(function () use ($command, $hqId, $input): CreateTaskResult {
            $this->taskValidator->validateDraft($hqId, $input);

            $at = $this->clock->now();
            $task = $this->taskRepository->create([
                'hq_id' => $hqId,
                'title' => $input->title,
                'status' => TaskStatus::OPEN->value,
                'priority' => $input->priority->value,
                'description' => $input->description,
                'due_at' => $input->dueAt,
                'remind_at' => $input->remindAt,
                'customer_id' => $input->customerId,
                'opportunity_id' => $input->opportunityId,
                'assignee_id' => $input->assigneeId,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            // Every task opens its own history, so the queue can always be read back as a sequence of
            // moves rather than a current owner with no past.
            $event = $this->taskAssignmentEventRepository->create([
                'hq_id' => $hqId,
                'task_id' => $task->task_id,
                'event_type' => TaskAssignmentEventType::CREATE->value,
                // The team is context the work passed through; it never owns the row.
                'to_team_id' => $input->teamContextId,
                'to_user_id' => $input->assigneeId,
                'actor_id' => $command->actor->userId,
                'occurred_at' => $at,
                'operation_key' => $input->operationKey ?? 'create:'.$task->task_id,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            return new CreateTaskResult(
                $this->taskRepository->findForTenant($hqId, $task->task_id) ?? $task,
                $event,
            );
        }, attempts: 3);
    }
}
