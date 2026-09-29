<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

/** An Offering whose commitment cannot be promised right now; listing flows omit it instead of failing. */
enum CommitmentUnavailability: string
{
    /** Key into lang/<locale>/api.php. */
    public function messageKey(): string
    {
        return match ($this) {
            self::CatalogDependencyUnavailable => 'pricing.catalog_dependency_is_inactive_unavailable',
            self::CommitmentScopeUnavailable => 'servicecatalog.commitment_scope_unavailable',
            self::CalendarUnavailable => 'servicecatalog.sla_calendar_unavailable',
            self::PickupWindowInvalid => 'servicecatalog.pickup_window_is_invalid',
            self::DeliveryWindowInvalid => 'servicecatalog.delivery_window_is_invalid',
        };
    }

    public static function tryFromRuleViolation(CommitmentFailure $failure): ?self
    {
        return match ($failure) {
            CommitmentFailure::CalendarUnavailable => self::CalendarUnavailable,
            CommitmentFailure::PickupWindowInvalid => self::PickupWindowInvalid,
            CommitmentFailure::DeliveryWindowInvalid => self::DeliveryWindowInvalid,
            CommitmentFailure::PickupWindowRequired, CommitmentFailure::DeliveryWindowRequired => null,
        };
    }

    public static function fromWindowType(CommitmentWindowType $windowType): self
    {
        return match ($windowType) {
            CommitmentWindowType::Pickup => self::PickupWindowInvalid,
            CommitmentWindowType::Delivery => self::DeliveryWindowInvalid,
        };
    }
    case CatalogDependencyUnavailable = 'CATALOG_DEPENDENCY_UNAVAILABLE';
    case CommitmentScopeUnavailable = 'COMMITMENT_SCOPE_UNAVAILABLE';
    case CalendarUnavailable = 'SLA_CALENDAR_UNAVAILABLE';
    case PickupWindowInvalid = 'PICKUP_WINDOW_INVALID';
    case DeliveryWindowInvalid = 'DELIVERY_WINDOW_INVALID';
}
