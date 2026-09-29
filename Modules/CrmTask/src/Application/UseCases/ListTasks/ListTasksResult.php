<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\ListTasks;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTask\Application\Dto\TaskSummaryDto;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

/**
 * The task inbox: the counters, the rows of the selected bucket, and the names of the customers and
 * opportunities those rows point at, read through the ports in one call each.
 */
final readonly class ListTasksResult
{
    /**
     * @param  Collection<int, TaskRecord>  $tasks
     * @param  array<string, string|null>  $customerNames
     * @param  array<string, string|null>  $opportunityTitles
     */
    public function __construct(
        public TaskSummaryDto $summary,
        public Collection $tasks,
        public array $customerNames,
        public array $opportunityTitles,
    ) {}
}
