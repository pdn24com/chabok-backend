<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\CreateTask;

use Modules\CrmTask\Infrastructure\Persistence\Models\TaskAssignmentEventRecord;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

/** The raised task and the CREATE row that opens its assignment history. */
final readonly class CreateTaskResult
{
    public function __construct(
        public TaskRecord $task,
        public TaskAssignmentEventRecord $assignmentEvent,
    ) {}
}
