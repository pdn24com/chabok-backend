<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum CoverageTarget: string
{
    case PickupServiceArea = 'PICKUP_SERVICE_AREA';
    case DestinationGateway = 'DESTINATION_GATEWAY';
    case LastMileNode = 'LAST_MILE_NODE';
}
