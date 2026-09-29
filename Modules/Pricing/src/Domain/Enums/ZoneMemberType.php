<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum ZoneMemberType: string
{
    public function precedence(): int
    {
        return match ($this) {
            self::EXPLICIT_OVERRIDE => 400,
            self::POSTAL_RANGE => 300,
            self::POLYGON => 250,
            self::CITY => 200,
            self::PROVINCE => 100,
        };
    }
    case EXPLICIT_OVERRIDE = 'EXPLICIT_OVERRIDE';
    case POSTAL_RANGE = 'POSTAL_RANGE';
    case POLYGON = 'POLYGON';
    case CITY = 'CITY';
    case PROVINCE = 'PROVINCE';
}
