<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Adapters;

use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Domain\ValueObjects\AreaEdge;
use Modules\Organization\Infrastructure\Persistence\Models\AreaHierarchyRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class EloquentScopeTopology implements ScopeTopologyInterface
{
    public function areaEdges(string $hqId): array
    {
        return AreaHierarchyRecord::query()
            ->where('hq_id', $hqId)
            ->with(['parent:id,area_id', 'child:id,area_id'])->get(['parent_area_id', 'child_area_id'])
            ->map(fn (AreaHierarchyRecord $edge): AreaEdge => new AreaEdge($edge->parent->area_id, $edge->child->area_id))
            ->all();
    }

    public function nodeIds(
        string $hqId,
        bool $all,
        array $nodeIds,
        array $areaIds,
        bool $activeOnly,
    ): array {
        return NodeRecord::query()
            ->where('hq_id', $hqId)
            ->when($activeOnly, fn ($query) => $query->where('status', 'ACTIVE'))
            ->when(! $all, fn ($query) => $query->where(fn ($query) => $query->whereIn('node_id', $nodeIds)->orWhereIn('area_id', $areaIds)))
            ->orderBy('node_id')
            ->pluck('node_id')
            ->all();
    }

    public function nodeAreas(string $hqId): array
    {
        return NodeRecord::query()->where('hq_id', $hqId)->pluck('area_id', 'node_id')->all();
    }
}
