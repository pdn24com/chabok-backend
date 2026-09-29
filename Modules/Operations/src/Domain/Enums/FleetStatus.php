<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum FleetStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
