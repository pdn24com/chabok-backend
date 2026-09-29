<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Serialization;

use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

/** Stable public/audit document; internal flows keep the native model. */
final class NetworkDocument
{
    public static function serialize(AreaRecord|NodeRecord $record): array
    {
        return $record instanceof AreaRecord ? self::area($record) : self::node($record);
    }

    public static function area(AreaRecord $row): array
    {
        return [
            'area_id' => (string) $row->area_id,
            'area_code' => (string) $row->area_code,
            'area_title' => (string) $row->area_title,
            'parent_area_id' => $row->parentEdge?->parent?->area_id,
            'status' => (string) $row->status,
            'version' => (int) $row->version,
        ];
    }

    public static function node(NodeRecord $row): array
    {
        return [
            'node_id' => (string) $row->node_id,
            'area_id' => (string) $row->area_id,
            'node_code' => (string) $row->node_code,
            'node_title' => (string) $row->node_title,
            'node_type' => (string) $row->node_type,
            'capabilities' => $row->capabilities ?? [],
            'address' => self::address($row),
            'status' => (string) $row->status,
            'version' => (int) $row->version,
        ];
    }

    public static function address(NodeRecord $row): array
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
}
