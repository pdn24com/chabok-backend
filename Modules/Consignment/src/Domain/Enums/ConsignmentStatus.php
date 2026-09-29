<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

/**
 * System operational status codes seeded into `operational_statuses`.
 *
 * Tenants may add their own codes, so a raw column value is not guaranteed to
 * resolve here; use tryFrom() when the code originates from tenant data.
 */
enum ConsignmentStatus: string
{
    public function group(): ?ConsignmentStatusGroup
    {
        return match ($this) {
            self::Draft => null,
            self::Confirmed, self::PickupAssigned => ConsignmentStatusGroup::NewRouted,
            self::PickedUp, self::InboundReceived, self::Routed,
            self::OutboundConfirmed, self::OutboundSent, self::CheckedIn, self::OutForDelivery => ConsignmentStatusGroup::InOperation,
            self::PickupFailed, self::DeliveryFailed, self::ReturnHold, self::ReturnCustomerHold => ConsignmentStatusGroup::Exception,
            self::Delivered => ConsignmentStatusGroup::Completed,
            self::Returned, self::Abandoned => ConsignmentStatusGroup::Cancelled,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, self::terminal(), true);
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /** Statuses that close a consignment out; nothing further is expected. @return list<self> */
    public static function terminal(): array
    {
        return [self::Delivered, self::Returned, self::Abandoned];
    }

    /** Statuses still moving through the network. @return list<self> */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->isActive()));
    }

    /** Statuses raised as operational exceptions. @return list<self> */
    public static function exceptions(): array
    {
        return [self::PickupFailed, self::DeliveryFailed];
    }

    /** Statuses eligible for a pickup commitment window. @return list<self> */
    public static function awaitingPickup(): array
    {
        return [self::Draft, self::Confirmed, self::PickupAssigned, self::PickupFailed];
    }

    /**
     * @param  list<self>  $cases
     * @return list<string>
     */
    public static function valuesOf(array $cases): array
    {
        return array_column($cases, 'value');
    }

    /** @return list<string> */
    public static function values(): array
    {
        return self::valuesOf(self::cases());
    }
    case Draft = 'D00';
    case Confirmed = 'CFM';
    case PickupAssigned = 'PD';
    case PickedUp = 'PU';
    case PickupFailed = 'NPU';
    case InboundReceived = 'IR';
    case Routed = 'ROU';
    case OutboundConfirmed = 'OF';
    case OutboundSent = 'OS';
    case CheckedIn = 'CI';
    case OutForDelivery = 'OD';
    case Delivered = 'OK';
    case DeliveryFailed = 'NOK';
    case ReturnHold = 'RH';
    case ReturnCustomerHold = 'RCH';
    case Returned = 'RO';
    case Abandoned = 'AA';
}
