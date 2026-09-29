<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum ZonePolicy: string
{
    case DIRECTIONAL = 'DIRECTIONAL';
    case HIGHER_ZONE_RANK = 'HIGHER_ZONE_RANK';
}
