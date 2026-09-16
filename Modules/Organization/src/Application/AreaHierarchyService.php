<?php

declare(strict_types=1);

namespace Modules\Organization\Application;

final readonly class AreaHierarchyService
{
    public function __construct(
        private \Modules\Organization\Application\UseCases\AddAreaEdge\AddAreaEdgeHandler $addAreaEdge,
        private \Modules\Organization\Application\UseCases\ListDescendantAreas\ListDescendantAreasHandler $listDescendantAreas,
    )
    {
    }

    public function addEdge(string $hqId, string $parentAreaId, string $childAreaId): void
    {
        $this->addAreaEdge->handle(new \Modules\Organization\Application\UseCases\AddAreaEdge\AddAreaEdgeCommand($hqId, $parentAreaId, $childAreaId));
    }

    public function descendantIds(string $hqId, string $areaId): array
    {
        return $this->listDescendantAreas->handle(new \Modules\Organization\Application\UseCases\ListDescendantAreas\ListDescendantAreasCommand($hqId, $areaId))->data;
    }
}
