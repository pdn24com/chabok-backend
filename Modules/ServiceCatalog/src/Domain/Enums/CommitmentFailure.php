<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentFailure: string
{
    /** Key into lang/<locale>/api.php. */
    public function messageKey(): string
    {
        return match ($this) {
            self::CalendarUnavailable => 'servicecatalog.sla_calendar_unavailable',
            self::PickupWindowInvalid => 'servicecatalog.pickup_window_is_invalid',
            self::PickupWindowRequired => 'servicecatalog.pickup_window_is_required',
            self::DeliveryWindowInvalid => 'servicecatalog.delivery_window_is_invalid',
            self::DeliveryWindowRequired => 'servicecatalog.delivery_window_is_required',
        };
    }
    case CalendarUnavailable = 'SLA_CALENDAR_UNAVAILABLE';
    case PickupWindowInvalid = 'PICKUP_WINDOW_INVALID';
    case PickupWindowRequired = 'PICKUP_WINDOW_REQUIRED';
    case DeliveryWindowInvalid = 'DELIVERY_WINDOW_INVALID';
    case DeliveryWindowRequired = 'DELIVERY_WINDOW_REQUIRED';
}
