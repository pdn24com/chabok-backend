<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Application\Dto\NetworkFiltersDto;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\Organization\Domain\Enums\NetworkStatus;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class EloquentNodeRepository implements NodeRepositoryInterface
{
    /** Columns a Node directory listing needs; a full row is never sent to a picker. */
    private const DIRECTORY_COLUMNS = ['node_id', 'node_code', 'node_title', 'node_type', 'status', 'area_id'];

    public function findByTenant(string $hqId, string $nodeId): ?NodeRecord
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->first();
    }

    public function findActive(string $hqId, string $nodeId): ?NodeRecord
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => NetworkStatus::ACTIVE->value])->first();
    }

    public function activeExists(string $hqId, string $nodeId): bool
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'status' => NetworkStatus::ACTIVE->value])->exists();
    }

    public function codeExists(string $hqId, string $code): bool
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'node_code' => $code])->exists();
    }

    public function lockByTenant(string $hqId, string $nodeId): ?NodeRecord
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId])->lockForUpdate()->first();
    }

    public function tenantOf(string $nodeId): ?string
    {
        $node = NodeRecord::query()->where('node_id', $nodeId)->first(['hq_id']);

        return $node === null ? null : (string) $node->hq_id;
    }

    public function activeIdsAmong(string $hqId, array $nodeIds): array
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'status' => NetworkStatus::ACTIVE->value])
            ->whereIn('node_id', array_unique($nodeIds))->pluck('node_id')->map(strval(...))->all();
    }

    public function idsAmong(string $hqId, array $nodeIds): array
    {
        return NodeRecord::query()->where('hq_id', $hqId)->whereIn('node_id', $nodeIds)->pluck('node_id')->map(strval(...))->all();
    }

    public function activeIdsInScopes(string $hqId, array $areaIds, array $nodeIds, bool $tenantWide): array
    {
        $query = NodeRecord::query()->where('hq_id', $hqId)->where('status', NetworkStatus::ACTIVE->value);
        if (! $tenantWide) {
            if ($areaIds === [] && $nodeIds === []) {
                return [];
            }
            $query->where(function ($query) use ($areaIds, $nodeIds): void {
                if ($areaIds !== []) {
                    $query->whereIn('area_id', array_values(array_unique($areaIds)));
                }
                if ($nodeIds !== []) {
                    $areaIds === [] ? $query->whereIn('node_id', $nodeIds) : $query->orWhereIn('node_id', $nodeIds);
                }
            });
        }

        return $query->orderBy('node_id')->pluck('node_id')->map(fn ($id) => (string) $id)->all();
    }

    public function hasActiveInArea(string $hqId, string $areaId): bool
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'area_id' => $areaId, 'status' => NetworkStatus::ACTIVE->value])->exists();
    }

    public function directoryEntries(string $hqId, array $nodeIds, bool $activeOnly): array
    {
        return NodeRecord::query()
            ->where('hq_id', $hqId)
            ->whereIn('node_id', $nodeIds)
            ->when($activeOnly, fn ($query) => $query->where('status', NetworkStatus::ACTIVE->value))
            ->orderBy('node_title')
            ->get(self::DIRECTORY_COLUMNS)
            ->all();
    }

    public function search(string $hqId, array $visibleIds, NetworkFiltersDto $filters): LengthAwarePaginator
    {
        $query = NodeRecord::query()->where('hq_id', $hqId)->whereIn('node_id', $visibleIds);
        if ($filters->search !== '') {
            $search = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('node_code', 'like', $search)->orWhere('node_title', 'like', $search));
        }
        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }
        if ($filters->areaId !== null) {
            $query->where('area_id', $filters->areaId);
        }
        if ($filters->nodeType !== null) {
            $query->where('node_type', $filters->nodeType->value);
        }

        return $query->orderBy('node_title')->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    public function create(array $attributes): NodeRecord
    {
        return NodeRecord::query()->forceCreate($attributes);
    }

    public function apply(NodeRecord $node, array $changes): void
    {
        $node->forceFill($changes)->save();
    }
}
