<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Repositories;

use Modules\CrmTask\Infrastructure\Persistence\Models\TaskAssignmentEventRecord;

interface TaskAssignmentEventRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TaskAssignmentEventRecord;

    /** The event a repeated submission already wrote, so the same operation key never moves a task twice. */
    public function findByOperationKey(string $hqId, string $taskId, string $operationKey): ?TaskAssignmentEventRecord;
}
