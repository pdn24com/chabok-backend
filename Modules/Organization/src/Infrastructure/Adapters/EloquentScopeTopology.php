<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Adapters;

use Modules\Foundation\Application\Contracts\ScopeTopology;
use Modules\Organization\Infrastructure\Persistence\Models\AreaHierarchyRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class EloquentScopeTopology implements ScopeTopology
{
    public function areaEdges(string $hqId): array
    {
        return AreaHierarchyRecord::query()->where('hq_id', $hqId)->toBase()->get(['parent_area_id', 'child_area_id'])->all();
    }

    public function nodeIds(string $hqId, bool $all, array $nodeIds, array $areaIds, bool $activeOnly): array
    {
        return NodeRecord::query()->where('hq_id', $hqId)->when($activeOnly, fn($query) => $query->where('status', 'ACTIVE'))->when(!$all, fn($query) => $query->where(fn($query) => $query->whereIn('node_id', $nodeIds)->orWhereIn('area_id', $areaIds)))->orderBy('node_id')->pluck('node_id')->all();
    }

    public function nodeBelongsToAreas(string $hqId, ?string $nodeId, array $areaIds): bool
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->whereIn('area_id', $areaIds)->exists();
    }
}
