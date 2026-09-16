<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Data\Page;
use Modules\Organization\Application\Repositories\NetworkRepository;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;
use Modules\Organization\Infrastructure\Persistence\Models\AreaHierarchyRecord;

final class EloquentNetworkRepository implements NetworkRepository
{
    public function paginateAreas(string $hqId, array $filters, array $visibleIds): Page
    {
        $query = AreaRecord::query()->toBase()->from('areas as a')->where('a.hq_id', $hqId)->leftJoin('area_hierarchies as h', fn($join) => $join->on('h.hq_id', '=', 'a.hq_id')->on('h.child_area_id', '=', 'a.area_id'))->select(['a.*', 'h.parent_area_id'])->whereIn('a.area_id', $visibleIds);
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $query->where(fn($q) => $q->where('a.area_code', 'like', $search)->orWhere('a.area_title', 'like', $search));
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('a.status', $filters['status']);
        }
        $page = $query->orderBy('a.area_title')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function paginateNodes(string $hqId, array $filters, array $visibleIds): Page
    {
        $query = NodeRecord::query()->toBase()->where('hq_id', $hqId)->whereIn('node_id', $visibleIds);
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $query->where(fn($q) => $q->where('node_code', 'like', $search)->orWhere('node_title', 'like', $search));
        }
        foreach (['status', 'area_id', 'node_type'] as $filter) {
            if (($filters[$filter] ?? '') !== '') {
                $query->where($filter, $filters[$filter]);
            }
        }
        $page = $query->orderBy('node_title')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function areaCodeExists(string $hqId, string $code): bool
    {
        return AreaRecord::query()->toBase()->where(['hq_id' => $hqId, 'area_code' => $code])->exists();
    }

    public function lockArea(string $hqId, string $areaId): ?object
    {
        return AreaRecord::query()->toBase()->where(['hq_id' => $hqId, 'area_id' => $areaId])->lockForUpdate()->first();
    }

    public function hasActiveNodes(string $hqId, string $areaId): bool
    {
        return NodeRecord::query()->toBase()->where(['hq_id' => $hqId, 'area_id' => $areaId, 'status' => 'ACTIVE'])->exists();
    }

    public function hasActiveChildren(string $hqId, string $areaId): bool
    {
        return AreaHierarchyRecord::query()->toBase()->from('area_hierarchies as h')->join('areas as a', 'a.area_id', '=', 'h.child_area_id')->where(['h.hq_id' => $hqId, 'h.parent_area_id' => $areaId, 'a.status' => 'ACTIVE'])->exists();
    }

    public function node(string $hqId, string $nodeId): ?object
    {
        return NodeRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->first();
    }

    public function nodeCodeExists(string $hqId, string $code): bool
    {
        return NodeRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_code' => $code])->exists();
    }

    public function lockNode(string $hqId, string $nodeId): ?object
    {
        return NodeRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->lockForUpdate()->first();
    }

    public function areaWithParent(string $hqId, string $areaId): ?object
    {
        return AreaRecord::query()->toBase()->from('areas as a')->leftJoin('area_hierarchies as h', fn($join) => $join->on('h.hq_id', '=', 'a.hq_id')->on('h.child_area_id', '=', 'a.area_id'))->where(['a.hq_id' => $hqId, 'a.area_id' => $areaId])->select(['a.*', 'h.parent_area_id'])->first();
    }

    public function activeAreaExists(string $hqId, string $areaId): bool
    {
        return AreaRecord::query()->toBase()->where(['hq_id' => $hqId, 'area_id' => $areaId, 'status' => 'ACTIVE'])->exists();
    }

    public function activeCity(string $cityId): ?object
    {
        return DB::table('cities')->where(['city_id' => $cityId, 'is_active' => true])->first();
    }

    public function activeProvinceExists(string $provinceId): bool
    {
        return DB::table('provinces')->where(['province_id' => $provinceId, 'is_active' => true])->exists();
    }

    public function removeParent(string $hqId, string $areaId): int
    {
        return AreaHierarchyRecord::query()->toBase()->where(['hq_id' => $hqId, 'child_area_id' => $areaId])->delete();
    }

    public function insertArea(array $attributes): void
    {
        AreaRecord::query()->toBase()->insert($attributes);
    }

    public function updateArea(string $areaId, array $attributes): void
    {
        AreaRecord::query()->toBase()->where('area_id', $areaId)->update($attributes);
    }

    public function insertNode(array $attributes): void
    {
        NodeRecord::query()->toBase()->insert($attributes);
    }

    public function updateNode(string $nodeId, array $attributes): void
    {
        NodeRecord::query()->toBase()->where('node_id', $nodeId)->update($attributes);
    }

    public function insertHierarchy(array $attributes): void
    {
        AreaHierarchyRecord::query()->toBase()->insert($attributes);
    }

    public function areaIds(string $hqId): array
    {
        return AreaRecord::query()->toBase()->where('hq_id', $hqId)->pluck('area_id')->all();
    }

    public function wouldCreateCycle(string $hqId, string $areaId, string $parentId): bool
    {
        $row = DB::selectOne('WITH RECURSIVE descendants AS (SELECT child_area_id FROM area_hierarchies WHERE hq_id = ? AND parent_area_id = ? UNION ALL SELECT h.child_area_id FROM area_hierarchies h JOIN descendants d ON h.parent_area_id = d.child_area_id WHERE h.hq_id = ?) SELECT EXISTS(SELECT 1 FROM descendants WHERE child_area_id = ?) AS creates_cycle', [$hqId, $areaId, $hqId, $parentId]);
        return (bool) ($row->creates_cycle ?? false);
    }

    public function descendantIds(string $hqId, string $areaId): array
    {
        return array_map(static fn($row): string => (string) $row->area_id, DB::select(<<<'SQL'
        WITH RECURSIVE descendants AS (
            SELECT child_area_id AS area_id
            FROM area_hierarchies
            WHERE hq_id = ? AND parent_area_id = ?
            UNION ALL
            SELECT h.child_area_id
            FROM area_hierarchies h
            JOIN descendants d ON h.parent_area_id = d.area_id
            WHERE h.hq_id = ?
        )
        SELECT DISTINCT area_id FROM descendants
        SQL, [$hqId, $areaId, $hqId]));
    }

    public function lockAreas(string $hqId, array $areaIds): void
    {
        AreaRecord::query()->toBase()->where('hq_id', $hqId)->whereIn('area_id', $areaIds)->lockForUpdate()->get();
    }

    public function countAreas(string $hqId, array $areaIds): int
    {
        return AreaRecord::query()->toBase()->where('hq_id', $hqId)->whereIn('area_id', $areaIds)->count();
    }
}
