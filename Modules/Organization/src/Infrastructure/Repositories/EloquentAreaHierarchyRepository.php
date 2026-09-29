<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Organization\Application\Repositories\AreaHierarchyRepositoryInterface;
use Modules\Organization\Domain\Enums\NetworkStatus;
use Modules\Organization\Infrastructure\Persistence\Models\AreaHierarchyRecord;

final class EloquentAreaHierarchyRepository implements AreaHierarchyRepositoryInterface
{
    public function deleteParentEdges(string $hqId, string $areaId): void
    {
        AreaHierarchyRecord::query()->where('hq_id', $hqId)
            ->whereHas('child', fn (Builder $query) => $query->where('area_id', $areaId))->delete();
    }

    public function create(array $attributes): void
    {
        AreaHierarchyRecord::query()->forceCreate($attributes);
    }

    public function hasActiveChildren(string $hqId, string $areaId): bool
    {
        return AreaHierarchyRecord::query()
            ->where('hq_id', $hqId)->whereHas('parent', fn (Builder $query) => $query->where('area_id', $areaId))
            ->whereHas('child', fn (Builder $query) => $query->where('hq_id', $hqId)->where('status', NetworkStatus::ACTIVE->value))
            ->exists();
    }
}
