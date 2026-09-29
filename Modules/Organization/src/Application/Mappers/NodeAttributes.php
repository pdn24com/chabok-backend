<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Mappers;

use Modules\Organization\Application\Dto\NodeDetailsDto;
use Modules\Organization\Application\Serialization\NodeAddressDocument;
use Modules\Organization\Domain\Enums\NodeCapability;

final class NodeAttributes
{
    public static function fromDetails(NodeDetailsDto $input): array
    {
        $address = $input->address;

        return [
            'area_id' => $input->areaId,
            'node_title' => $input->title,
            'node_type' => $input->type->value,
            'capabilities' => array_map(fn (NodeCapability $capability): string => $capability->value, $input->capabilities),
            'address_snapshot' => NodeAddressDocument::serialize($address),
            'province_id' => $address->provinceId,
            'city_id' => $address->cityId,
            'country_code' => $address->countryCode,
            'postal_code' => $address->postalCode,
            'address_line' => $address->line,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];
    }
}
