<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum AmountRoundingMode: string
{
    case NONE = 'NONE';
    case CEIL = 'CEIL';
    case FLOOR = 'FLOOR';
    case HALF_UP = 'HALF_UP';
}
