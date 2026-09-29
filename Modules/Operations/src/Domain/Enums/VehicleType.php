<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum VehicleType: string
{
    case Motorcycle = 'MOTORCYCLE';
    case Car = 'CAR';
    case Van = 'VAN';
    case LightTruck = 'LIGHT_TRUCK';
    case Truck = 'TRUCK';
    case Trailer = 'TRAILER';
    case Other = 'OTHER';
}
