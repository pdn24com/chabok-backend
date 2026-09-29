<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Mappers;

use Modules\Foundation\Domain\ValueObjects\CoverageAddress;

final class CoverageAddressInput
{
    public static function fromArray(array $input): CoverageAddress
    {
        return new CoverageAddress(
            country: $input['country'] ?? null,
            state: $input['state'] ?? null,
            city: $input['city'] ?? null,
            cityId: $input['city_id'] ?? null,
            provinceId: $input['province_id'] ?? null,
            postalCode: $input['postal_code'] ?? null,
            latitude: $input['latitude'] ?? null,
            longitude: $input['longitude'] ?? null,
            zoneOverride: $input['zone_override'] ?? null,
            factKeys: array_keys($input),
            extensions: array_diff_key($input, array_flip(['country', 'state', 'city', 'city_id', 'province_id', 'postal_code', 'latitude', 'longitude', 'zone_override'])),
        );
    }
}
