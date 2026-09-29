<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

/** The counters above the task inbox. Each one counts the same rows the matching bucket lists. */
final readonly class TaskSummaryDto
{
    public function __construct(
        public int $open,
        public int $waitingCustomer,
        public int $today,
        public int $overdue,
        /** Tasks attached to no customer and no opportunity: the team's own housekeeping. */
        public int $internal,
    ) {}
}
