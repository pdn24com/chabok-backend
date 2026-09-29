<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

enum NumberRangeStatus: string
{
    case Available = 'AVAILABLE';
    case Disabled = 'DISABLED';
    case Exhausted = 'EXHAUSTED';
}
