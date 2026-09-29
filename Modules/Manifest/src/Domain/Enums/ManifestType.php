<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

enum ManifestType: string
{
    case PickupAssignment = 'PICKUP_ASSIGNMENT';
    case PickupCompletion = 'PICKUP_COMPLETION';
    case PickupException = 'PICKUP_EXCEPTION';
    case InboundReception = 'INBOUND_RECEPTION';
    case RouteRegistration = 'ROUTE_REGISTRATION';
    case OutboundTransfer = 'OUTBOUND_TRANSFER';
    case LinehaulDeparture = 'LINEHAUL_DEPARTURE';
    case TransitUnload = 'TRANSIT_UNLOAD';
    case DeliveryAssignment = 'DELIVERY_ASSIGNMENT';
    case DeliveryCompletion = 'DELIVERY_COMPLETION';
    case DeliveryException = 'DELIVERY_EXCEPTION';
}
