<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum CoverageCriterionType: string
{
    public function specificity(): int
    {
        return match ($this) {
            self::PROVINCE => 1,
            self::CITY => 2,
            self::POSTAL_RANGE => 3,
            self::POLYGON, self::POINT_RADIUS => 4,
        };
    }
    case PROVINCE = 'PROVINCE';
    case CITY = 'CITY';
    case POSTAL_RANGE = 'POSTAL_RANGE';
    case POLYGON = 'POLYGON';
    case POINT_RADIUS = 'POINT_RADIUS';
}
