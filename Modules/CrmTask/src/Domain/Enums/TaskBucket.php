<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

/** The slices the task inbox offers. Each one narrows the list; none of them changes its ordering. */
enum TaskBucket: string
{
    case OVERDUE = 'overdue';
    case TODAY = 'today';
    case FUTURE = 'future';
    case UNDATED = 'undated';
    case WAITING = 'waiting';
    case CLOSED = 'closed';
}
