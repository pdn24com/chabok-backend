<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Serialization;

use Modules\Organization\Application\Dto\NodeAddressDto;

final class NodeAddressDocument
{
    public static function serialize(NodeAddressDto $address): array
    {
        return ['country_code' => $address->countryCode, 'province_id' => $address->provinceId, 'city_id' => $address->cityId,
            'postal_code' => $address->postalCode, 'line' => $address->line,
            'location' => $address->latitude === null ? null : ['latitude' => $address->latitude, 'longitude' => $address->longitude]];
    }
}
