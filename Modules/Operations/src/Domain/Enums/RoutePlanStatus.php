<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum RoutePlanStatus: string
{
    /** A Consignment may hold only one plan that is still being executed. @return list<string> */
    public static function openValues(): array
    {
        return [self::Planned->value, self::InProgress->value];
    }
    case Planned = 'PLANNED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Superseded = 'SUPERSEDED';
}
