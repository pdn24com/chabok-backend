<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum RoutePurpose: string
{
    case Trunk = 'TRUNK';
    case LastMile = 'LAST_MILE';
}
