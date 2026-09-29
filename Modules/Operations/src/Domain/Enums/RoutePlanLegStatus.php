<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum RoutePlanLegStatus: string
{
    case Pending = 'PENDING';
    case Routed = 'ROUTED';
    case OutboundConfirmed = 'OUTBOUND_CONFIRMED';
    case InTransit = 'IN_TRANSIT';
    case Arrived = 'ARRIVED';
    case Received = 'RECEIVED';
}
