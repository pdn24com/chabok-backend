<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentAnchor: string
{
    case ConsignmentCreated = 'CONSIGNMENT_CREATED';
    case PickupCompleted = 'PICKUP_COMPLETED';
    case PickupStart = 'PICKUP_COMMITMENT_START';
    case PickupEnd = 'PICKUP_COMMITMENT_END';
}
