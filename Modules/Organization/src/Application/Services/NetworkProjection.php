<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class NetworkProjection
{
    public function __construct(private \Modules\Organization\Application\Repositories\NetworkRepository $network)
    {
    }

    public function areaRow(string $hqId, string $areaId): object
    {
        $row = $this->network->areaWithParent($hqId, $areaId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function areaResource(object $row): array
    {
        return [
            'area_id' => (string) $row->area_id,
            'area_code' => (string) $row->area_code,
            'area_title' => (string) $row->area_title,
            'parent_area_id' => $row->parent_area_id === null ? null : (string) $row->parent_area_id,
            'status' => (string) $row->status,
            'version' => (int) $row->version,
        ];
    }

    public function nodeResource(object $row): array
    {
        return [
            'node_id' => (string) $row->node_id,
            'area_id' => (string) $row->area_id,
            'node_code' => (string) $row->node_code,
            'node_title' => (string) $row->node_title,
            'node_type' => (string) $row->node_type,
            'capabilities' => json_decode((string) ($row->capabilities ?? '[]'), true, 512, JSON_THROW_ON_ERROR),
            'address' => $this->addressResource($row),
            'status' => (string) $row->status,
            'version' => (int) $row->version,
        ];
    }

    public function addressResource(object $row): array
    {
        return [
            'country_code' => 'IR',
            'province_id' => $row->province_id === null ? null : (string) $row->province_id,
            'city_id' => $row->city_id === null ? null : (string) $row->city_id,
            'postal_code' => $row->postal_code === null ? null : (string) $row->postal_code,
            'line' => $row->address_line === null ? null : (string) $row->address_line,
            'location' => $row->latitude === null ? null : ['latitude' => (float) $row->latitude, 'longitude' => (float) $row->longitude],
        ];
    }

    public function nodeColumns(array $input): array
    {
        $address = $input['address'];
        $location = $address['location'] ?? null;
        return [
            'area_id' => $input['area_id'],
            'node_title' => $input['node_title'],
            'node_type' => $input['node_type'],
            'capabilities' => json_encode(array_values(array_unique($input['capabilities'])), JSON_THROW_ON_ERROR),
            'address_snapshot' => json_encode($address, JSON_THROW_ON_ERROR),
            'province_id' => $address['province_id'] ?? null,
            'city_id' => $address['city_id'] ?? null,
            'country_code' => 'IR',
            'postal_code' => $address['postal_code'] ?? null,
            'address_line' => $address['line'] ?? null,
            'latitude' => $location['latitude'] ?? null,
            'longitude' => $location['longitude'] ?? null,
        ];
    }
}
