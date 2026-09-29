<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum FleetAvailability: string
{
    case Available = 'AVAILABLE';
    case OnMission = 'ON_MISSION';
    case TemporarilyInactive = 'TEMPORARILY_INACTIVE';
    case Maintenance = 'MAINTENANCE';
    case Inactive = 'INACTIVE';
}
