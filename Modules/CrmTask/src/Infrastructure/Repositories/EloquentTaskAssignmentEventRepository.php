<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Repositories;

use Modules\CrmTask\Application\Repositories\TaskAssignmentEventRepositoryInterface;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskAssignmentEventRecord;

final class EloquentTaskAssignmentEventRepository implements TaskAssignmentEventRepositoryInterface
{
    public function create(array $attributes): TaskAssignmentEventRecord
    {
        return TaskAssignmentEventRecord::query()->forceCreate($attributes);
    }

    public function findByOperationKey(string $hqId, string $taskId, string $operationKey): ?TaskAssignmentEventRecord
    {
        return TaskAssignmentEventRecord::query()
            ->where(['hq_id' => $hqId, 'task_id' => $taskId, 'operation_key' => $operationKey])
            ->first();
    }
}
