<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListDescendantAreas;

use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;

final readonly class ListDescendantAreasHandler
{
    public function __construct(private ScopeTopologyInterface $scopeTopology) {}

    public function handle(ListDescendantAreasCommand $command): array
    {
        $hqId = $command->hqId;
        $areaId = $command->areaId;

        return (new AreaHierarchy($this->scopeTopology->areaEdges($hqId)))->descendants($areaId);
    }
}
