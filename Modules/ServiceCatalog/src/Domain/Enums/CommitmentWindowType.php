<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentWindowType: string
{
    case Pickup = 'PICKUP';
    case Delivery = 'DELIVERY';
}
