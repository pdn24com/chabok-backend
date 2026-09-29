<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum WeightRoundingMode: string
{
    case HalfUp = 'HALF_UP';
    case HalfEven = 'HALF_EVEN';
    case Ceiling = 'CEILING';
    case Floor = 'FLOOR';
    case StepUp = 'STEP_UP';
}
