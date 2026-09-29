<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum DriverOperationalType: string
{
    case Pickup = 'PICKUP';
    case Linehaul = 'LINEHAUL';
    case Delivery = 'DELIVERY';
    case Multi = 'MULTI';
}
