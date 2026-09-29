<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum DriverCapability: string
{
    public function displayOrder(): int
    {
        return match ($this) {
            self::Pickup => 1,
            self::Linehaul => 2,
            self::Delivery => 3,
        };
    }
    case Pickup = 'PICKUP';
    case Linehaul = 'LINEHAUL';
    case Delivery = 'DELIVERY';
}
