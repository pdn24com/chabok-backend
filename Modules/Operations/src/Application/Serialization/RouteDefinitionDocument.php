<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Serialization;

use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

/** Stable public document, also used for the immutable publication digest. */
final class RouteDefinitionDocument
{
    public static function definition(RouteDefinitionRecord $row): array
    {
        return [
            'route_definition_id' => (string) $row->route_definition_id,
            'route_code' => (string) $row->route_code,
            'route_title' => (string) $row->route_title,
            'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null,
        ];
    }

    public static function version(RouteDefinitionVersionRecord $row): array
    {
        return [
            'route_definition_version_id' => (string) $row->route_definition_version_id,
            'route_definition_id' => (string) $row->route_definition_id,
            'version_number' => (int) $row->version_number,
            'status' => (string) $row->status,
            'purpose' => (string) $row->purpose,
            'origin_node_id' => (string) $row->origin_node_id,
            'destination_node_id' => (string) $row->destination_node_id,
            'priority' => (int) $row->priority,
            'offering_version_id' => $row->offering_version_id ? (string) $row->offering_version_id : null,
            'effective_from' => $row->effective_from,
            'effective_to' => $row->effective_to,
            'version' => (int) $row->version,
            'legs' => $row->legs->map(fn (RouteDefinitionVersionLegRecord $leg): array => [
                'route_definition_leg_id' => (string) $leg->route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ])->all(),
        ];
    }
}
