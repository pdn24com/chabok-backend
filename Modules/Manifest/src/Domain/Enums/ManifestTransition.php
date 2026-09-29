<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

use Modules\Operations\Domain\Enums\DriverCapability;

enum ManifestTransition: string
{
    /** @return list<string> */
    public function sourceStatuses(): array
    {
        return match ($this) {
            self::PickupAssignment => ['CFM'],
            self::PickupCompletion, self::PickupException => ['PD'],
            self::Reception => ['PU', 'OS'],
            self::RouteRegistration, self::DeliveryAssignment => ['IR'],
            self::OutboundConfirmation => ['ROU', 'CI'],
            self::LinehaulDeparture => ['OF'],
            self::TransitUnload => ['OS'],
            self::DeliveryCompletion, self::DeliveryException => ['OD'],
        };
    }

    public function manifestType(): ManifestType
    {
        return match ($this) {
            self::PickupAssignment => ManifestType::PickupAssignment,
            self::PickupCompletion => ManifestType::PickupCompletion,
            self::PickupException => ManifestType::PickupException,
            self::Reception => ManifestType::InboundReception,
            self::RouteRegistration => ManifestType::RouteRegistration,
            self::OutboundConfirmation => ManifestType::OutboundTransfer,
            self::LinehaulDeparture => ManifestType::LinehaulDeparture,
            self::TransitUnload => ManifestType::TransitUnload,
            self::DeliveryAssignment => ManifestType::DeliveryAssignment,
            self::DeliveryCompletion => ManifestType::DeliveryCompletion,
            self::DeliveryException => ManifestType::DeliveryException,
        };
    }

    public function defaultContextType(): ManifestContextType
    {
        return match ($this) {
            self::Reception => ManifestContextType::PickupReception,
            self::OutboundConfirmation => ManifestContextType::OutboundConfirmation,
            default => ManifestContextType::from($this->manifestType()->value),
        };
    }

    /** @return list<ManifestContextType> */
    public function contextTypes(): array
    {
        return $this === self::Reception
            ? [ManifestContextType::PickupReception, ManifestContextType::MovementReception]
            : [$this->defaultContextType()];
    }

    public function requiredDriverCapability(): ?DriverCapability
    {
        return match ($this) {
            self::PickupAssignment => DriverCapability::Pickup,
            self::LinehaulDeparture => DriverCapability::Linehaul,
            self::DeliveryAssignment => DriverCapability::Delivery,
            default => null,
        };
    }
    case PickupAssignment = 'PD';
    case PickupCompletion = 'PU';
    case PickupException = 'NPU';
    case Reception = 'IR';
    case RouteRegistration = 'ROU';
    case OutboundConfirmation = 'OF';
    case LinehaulDeparture = 'OS';
    case TransitUnload = 'CI';
    case DeliveryAssignment = 'OD';
    case DeliveryCompletion = 'OK';
    case DeliveryException = 'NOK';
}
