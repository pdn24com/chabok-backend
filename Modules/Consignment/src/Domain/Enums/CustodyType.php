<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

enum CustodyType: string
{
    public function custodian(?string $driverId, string $nodeId): ?string
    {
        return match ($this) {
            self::PickupDriver, self::LinehaulDriver, self::DeliveryDriver => $driverId,
            self::Node => $nodeId,
            self::Recipient => null,
        };
    }

    /** A Parcel rests at a Node only while the Node itself holds custody. */
    public function restsAtNode(): bool
    {
        return $this === self::Node;
    }
    case Node = 'NODE';
    case PickupDriver = 'PICKUP_DRIVER';
    case LinehaulDriver = 'LINEHAUL_DRIVER';
    case DeliveryDriver = 'DELIVERY_DRIVER';
    case Recipient = 'RECIPIENT';
}
