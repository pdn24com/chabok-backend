<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface ScopeTopology
{
    /** @return list<object{parent_area_id: string, child_area_id: string}> */

    public function areaEdges(string $hqId): array;
    /** @param list<string> $nodeIds @param list<string> $areaIds @return list<string> */

    public function nodeIds(string $hqId, bool $all, array $nodeIds, array $areaIds, bool $activeOnly): array;
    /** @param list<string> $areaIds */

    public function nodeBelongsToAreas(string $hqId, ?string $nodeId, array $areaIds): bool;
}
