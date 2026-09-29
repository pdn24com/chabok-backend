<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

enum ManifestContextType: string
{
    case PickupAssignment = 'PICKUP_ASSIGNMENT';
    case PickupCompletion = 'PICKUP_COMPLETION';
    case PickupException = 'PICKUP_EXCEPTION';
    case PickupReception = 'PICKUP_RECEPTION';
    case MovementReception = 'MOVEMENT_RECEPTION';
    case RouteRegistration = 'ROUTE_REGISTRATION';
    case OutboundConfirmation = 'OUTBOUND_CONFIRMATION';
    case LinehaulDeparture = 'LINEHAUL_DEPARTURE';
    case TransitUnload = 'TRANSIT_UNLOAD';
    case DeliveryAssignment = 'DELIVERY_ASSIGNMENT';
    case DeliveryCompletion = 'DELIVERY_COMPLETION';
    case DeliveryException = 'DELIVERY_EXCEPTION';
}
