<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum DeliveryTaskStatus: string
{
    case Pending = 'PENDING';
    case Assigned = 'ASSIGNED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
