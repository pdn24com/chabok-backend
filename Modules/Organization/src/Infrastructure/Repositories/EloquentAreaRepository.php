<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Application\Dto\NetworkFiltersDto;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Organization\Domain\Enums\NetworkStatus;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;

final class EloquentAreaRepository implements AreaRepositoryInterface
{
    public function findWithParentEdge(string $hqId, string $areaId): ?AreaRecord
    {
        return AreaRecord::query()
            ->where(['hq_id' => $hqId, 'area_id' => $areaId])
            ->with(['parentEdge' => fn (HasOne $query) => $query->where('hq_id', $hqId)->with('parent')])
            ->first();
    }

    public function lockByTenant(string $hqId, string $areaId): ?AreaRecord
    {
        return AreaRecord::query()->where(['hq_id' => $hqId, 'area_id' => $areaId])->lockForUpdate()->first();
    }

    public function activeExists(string $hqId, string $areaId): bool
    {
        return AreaRecord::query()->where(['hq_id' => $hqId, 'area_id' => $areaId, 'status' => NetworkStatus::ACTIVE->value])->exists();
    }

    public function codeExists(string $hqId, string $code): bool
    {
        return AreaRecord::query()->where(['hq_id' => $hqId, 'area_code' => $code])->exists();
    }

    public function idsByTenant(string $hqId): array
    {
        return AreaRecord::query()->where('hq_id', $hqId)->pluck('area_id')->all();
    }

    public function activeIdsAmong(string $hqId, array $areaIds): array
    {
        return AreaRecord::query()->where('hq_id', $hqId)->where('status', NetworkStatus::ACTIVE->value)
            ->whereIn('area_id', $areaIds)->pluck('area_id')->map(strval(...))->all();
    }

    public function activeByTitleKeyedById(string $hqId): array
    {
        return AreaRecord::query()
            ->where('hq_id', $hqId)
            ->where('status', NetworkStatus::ACTIVE->value)
            ->orderBy('area_title')
            ->get()
            ->keyBy('area_id')
            ->all();
    }

    public function byIdsKeyedByAreaId(string $hqId, array $areaIds): Collection
    {
        return AreaRecord::query()->where('hq_id', $hqId)->whereIn('area_id', $areaIds)->get()->keyBy('area_id');
    }

    public function lockByIdsKeyedByAreaId(string $hqId, array $areaIds): Collection
    {
        return AreaRecord::query()->where('hq_id', $hqId)->whereIn('area_id', $areaIds)->orderBy('id')->lockForUpdate()->get()->keyBy('area_id');
    }

    public function search(string $hqId, array $visibleIds, NetworkFiltersDto $filters): LengthAwarePaginator
    {
        $query = AreaRecord::query()
            ->where('hq_id', $hqId)
            ->with(['parentEdge' => fn (HasOne $query) => $query->where('hq_id', $hqId)->with('parent')])
            ->whereIn('area_id', $visibleIds);
        if ($filters->search !== '') {
            $search = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('area_code', 'like', $search)->orWhere('area_title', 'like', $search));
        }
        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        return $query->orderBy('area_title')->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    public function create(array $attributes): AreaRecord
    {
        return AreaRecord::query()->forceCreate($attributes);
    }

    public function apply(AreaRecord $area, array $changes): void
    {
        $area->forceFill($changes)->save();
    }
}
