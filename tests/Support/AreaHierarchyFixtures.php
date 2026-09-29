<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Organization\Application\UseCases\AddAreaEdge\AddAreaEdgeCommand;
use Modules\Organization\Application\UseCases\AddAreaEdge\AddAreaEdgeHandler;
use Modules\Organization\Application\UseCases\ListDescendantAreas\ListDescendantAreasCommand;
use Modules\Organization\Application\UseCases\ListDescendantAreas\ListDescendantAreasHandler;

final readonly class AreaHierarchyFixtures
{
    public function __construct(private AddAreaEdgeHandler $addAreaEdge, private ListDescendantAreasHandler $listDescendantAreas) {}

    public function addEdge(
        string $hqId,
        string $parentAreaId,
        string $childAreaId,
    ): void {
        $this->addAreaEdge->handle(new AddAreaEdgeCommand($hqId, $parentAreaId, $childAreaId));
    }

    public function descendantIds(string $hqId, string $areaId): array
    {
        return $this->listDescendantAreas->handle(new ListDescendantAreasCommand($hqId, $areaId));
    }
}
