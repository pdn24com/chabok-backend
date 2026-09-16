<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class RouteDefinitionReader
{
    public function __construct(private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes)
    {
    }

    public function presentVersion(object $row): array
    {
        return $this->versionArray($row);
    }

    public function legInputs(string $hq, string $versionId): array
    {
        if (!$this->routes->versionExists($hq, $versionId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Source version not found.');
        }
        return array_map(fn($leg): array => [
            'leg_order' => (int) $leg->leg_order,
            'origin_node_id' => (string) $leg->origin_node_id,
            'destination_node_id' => (string) $leg->destination_node_id,
        ], $this->routes->versionLegs($versionId));
    }

    public function versionRow(string $hq, string $definition, string $version): object
    {
        $row = $this->routes->version($hq, $definition, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function lockedVersion(string $hq, string $definition, string $version): object
    {
        $row = $this->routes->lockVersion($hq, $definition, $version);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function definitionArray(object $row): array
    {
        return [
            'route_definition_id' => (string) $row->route_definition_id,
            'route_code' => (string) $row->route_code,
            'route_title' => (string) $row->route_title,
            'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null,
        ];
    }

    public function versionArray(object $row): array
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
            'legs' => array_map(fn($leg): array => [
                'route_definition_leg_id' => (string) $leg->route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ], $this->routes->versionLegs($row->route_definition_version_id)),
        ];
    }
}
