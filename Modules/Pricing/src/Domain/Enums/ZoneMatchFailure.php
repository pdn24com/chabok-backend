<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum ZoneMatchFailure
{
    case Unresolved;
    case Ambiguous;
    case LatitudeRequired;
    case LongitudeRequired;
}
