<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\CompleteTask;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CompleteTaskHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private TaskAccessGuardInterface $accessGuard,
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function handle(CompleteTaskCommand $command): CompleteTaskResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);

        $task = $this->connection->transaction(function () use ($hqId, $command) {
            $current = $this->taskRepository->lockForTenant($hqId, $command->taskId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if (in_array($current->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['*' => ['task.task_is_already_closed']]);
            }

            $this->taskRepository->update($hqId, $command->taskId, [
                'status' => TaskStatus::COMPLETED->value,
                // The server owns the completion time; a client clock never decides when work ended.
                'completed_at' => $this->clock->now(),
                'completion_result' => $command->completionResult,
                // A finished task reminds nobody, and the database refuses a reminder on a closed row.
                'remind_at' => null,
            ]);

            return $this->taskRepository->findForTenant($hqId, $command->taskId) ?? $current;
        }, attempts: 3);

        return new CompleteTaskResult($task);
    }
}
