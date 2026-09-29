<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum CalculationMethod: string
{
    case FIXED = 'FIXED';
    case PER_UNIT = 'PER_UNIT';
    case SLAB = 'SLAB';
    case TIERED = 'TIERED';
    case PERCENT = 'PERCENT';
    case MIN_MAX = 'MIN_MAX';
}
