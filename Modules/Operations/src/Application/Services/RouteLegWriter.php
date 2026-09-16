<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

final readonly class RouteLegWriter
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function replaceLegs(string $hq, string $versionId, array $legs): void
    {
        $this->routes->deleteVersionLegs($versionId);
        foreach ($legs as $leg) {
            $this->routes->insertVersionLeg([
                'route_definition_version_leg_id' => $this->identifiers->uuid(),
                'hq_id' => $hq,
                'route_definition_version_id' => $versionId,
                'leg_order' => $leg['leg_order'],
                'origin_node_id' => $leg['origin_node_id'],
                'destination_node_id' => $leg['destination_node_id'],
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        }
    }

    public function syncLegacyLegs(object $version): void
    {
        $this->routes->deleteLegacyLegs($version->route_definition_id);
        foreach ($this->routes->versionLegs($version->route_definition_version_id) as $leg) {
            $this->routes->insertLegacyLeg([
                'route_definition_leg_id' => $this->identifiers->uuid(),
                'hq_id' => $version->hq_id,
                'route_definition_id' => $version->route_definition_id,
                'leg_order' => $leg->leg_order,
                'origin_node_id' => $leg->origin_node_id,
                'destination_node_id' => $leg->destination_node_id,
                'status' => 'ACTIVE',
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        }
    }
}
