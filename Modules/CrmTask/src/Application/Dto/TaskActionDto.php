<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

/** Recording what happened, and what that does to the task, as one submission. */
final readonly class TaskActionDto
{
    public function __construct(
        public ActivityDraftDto $activity,
        public TaskActionChangesDto $task,
    ) {}
}
