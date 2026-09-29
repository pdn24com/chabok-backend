<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;

final class GeographyIds
{
    public static function city(string $code): string
    {
        return (string) CityRecord::query()->where('legacy_city_code', $code)->sole()->getKey();
    }

    public static function province(string $code): string
    {
        return (string) ProvinceRecord::query()->where('legacy_province_code', $code)->sole()->getKey();
    }
}
