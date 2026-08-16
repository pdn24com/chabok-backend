<?php

declare(strict_types=1);

namespace Modules\Geography\Domain;

final class GeographyIds
{
    public static function province(string $legacyCode): string
    {
        return self::fromKey('province:'.$legacyCode);
    }

    public static function city(string $legacyCode): string
    {
        return self::fromKey('city:'.$legacyCode);
    }

    private static function fromKey(string $key): string
    {
        $hex = substr(hash('sha256', 'chabok-geography:v1:'.$key), 0, 32);
        $hex[12] = '5';
        $hex[16] = 'a';

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4)
            .'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}
