<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

use Modules\CrmTask\Domain\Enums\TaskBucket;
use Modules\CrmTask\Domain\Enums\TaskScope;

/** What narrows the task inbox. The bucket slices the list; the ordering never changes with it. */
final readonly class TaskListFiltersDto
{
    public function __construct(
        public TaskScope $scope = TaskScope::MINE,
        public ?TaskBucket $bucket = null,
        /** Free text matched against the title of the task. */
        public ?string $search = null,
    ) {}
}
