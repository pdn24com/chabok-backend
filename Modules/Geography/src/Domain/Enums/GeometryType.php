<?php

declare(strict_types=1);

namespace Modules\Geography\Domain\Enums;

use Modules\Geography\Domain\ValueObjects\Polygon;

enum GeometryType: string
{
    case POLYGON = 'Polygon';
    case MULTI_POLYGON = 'MultiPolygon';
}
