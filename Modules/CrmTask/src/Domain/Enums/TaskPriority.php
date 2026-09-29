<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum TaskPriority: string
{
    case VERY_HIGH = 'VERY_HIGH';
    case HIGH = 'HIGH';
    case MEDIUM = 'MEDIUM';
    case LOW = 'LOW';
    case VERY_LOW = 'VERY_LOW';
}
