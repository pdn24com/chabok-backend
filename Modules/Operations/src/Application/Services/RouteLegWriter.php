<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Operations\Application\Contracts\RouteLegWriterInterface;
use Modules\Operations\Application\Dto\RouteLegDto;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final readonly class RouteLegWriter implements RouteLegWriterInterface
{
    public function __construct(
        private ClockInterface $clock,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    /** @param list<RouteLegDto> $legs */
    public function replaceLegs(
        string $hq,
        string $versionId,
        array $legs,
    ): void {
        $rows = [];
        $at = $this->clock->now();
        foreach ($legs as $leg) {
            $rows[] = [

                'hq_id' => $hq,
                'route_definition_version_id' => $versionId,
                'leg_order' => $leg->order,
                'origin_node_id' => $leg->originNodeId,
                'destination_node_id' => $leg->destinationNodeId,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }
        $this->routeDefinitionRepository->replaceVersionLegs($versionId, $rows);
    }

    public function syncLegacyLegs(RouteDefinitionVersionRecord $version): void
    {
        $rows = [];
        $at = $this->clock->now();
        foreach ($version->loadMissing('legs')->legs as $leg) {
            $rows[] = [

                'hq_id' => $version->hq_id,
                'route_definition_id' => $version->route_definition_id,
                'leg_order' => $leg->leg_order,
                'origin_node_id' => $leg->origin_node_id,
                'destination_node_id' => $leg->destination_node_id,
                'status' => 'ACTIVE',
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }
        $this->routeDefinitionRepository->replaceDefinitionLegs((string) $version->route_definition_id, $rows);
    }
}
