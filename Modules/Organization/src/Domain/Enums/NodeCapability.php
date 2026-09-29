<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Enums;

enum NodeCapability: string
{
    case PICKUP = 'PICKUP';
    case CONSOLIDATION = 'CONSOLIDATION';
    case GATEWAY = 'GATEWAY';
    case LINEHAUL = 'LINEHAUL';
    case DELIVERY = 'DELIVERY';
    case CUSTOMER_HANDOFF = 'CUSTOMER_HANDOFF';
}
