<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\AssignTask;

use Modules\CrmTask\Infrastructure\Persistence\Models\TaskAssignmentEventRecord;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

/** The moved task and the history row that records the move. */
final readonly class AssignTaskResult
{
    public function __construct(
        public TaskAssignmentEventRecord $assignmentEvent,
        public TaskRecord $task,
    ) {}
}
