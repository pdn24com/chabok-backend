<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\RecordTaskAction;

use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityRecord;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

/** What was recorded, and the task as that record left it. */
final readonly class RecordTaskActionResult
{
    public function __construct(
        public ActivityRecord $activity,
        public TaskRecord $task,
    ) {}
}
