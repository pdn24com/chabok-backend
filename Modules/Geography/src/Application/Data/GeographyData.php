<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Data;

final class GeographyData
{
    /** @param array<string, mixed> $row @return array<string, mixed> */

    public static function provinceResource(array $row): array
    {
        return [
            'province_id' => $row['province_id'],
            'legacy_province_code' => (string) $row['legacy_province_code'],
            'name_fa' => $row['name_fa'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'is_active' => (bool) $row['is_active'],
        ];
    }
    /** @param array<string, mixed> $row @return array<string, mixed> */

    public static function cityResource(array $row): array
    {
        return [
            'city_id' => $row['city_id'],
            'legacy_city_code' => (string) $row['legacy_city_code'],
            'name_fa' => $row['name_fa'],
            'display_name' => sprintf('%s — %s (کد %s)', $row['name_fa'], $row['province_name_fa'], $row['legacy_city_code']),
            'is_active' => (bool) $row['is_active'],
            'province' => [
                'province_id' => $row['province_id'],
                'legacy_province_code' => (string) $row['legacy_province_code'],
                'name_fa' => $row['province_name_fa'],
            ],
        ];
    }
}
