<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Application\Dto\NetworkFiltersDto;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;

interface AreaRepositoryInterface
{
    /** Loads the Area with the parent edge of the same tenant, which every Area response renders. */
    public function findWithParentEdge(string $hqId, string $areaId): ?AreaRecord;

    public function lockByTenant(string $hqId, string $areaId): ?AreaRecord;

    public function activeExists(string $hqId, string $areaId): bool;

    public function codeExists(string $hqId, string $code): bool;

    /** @return list<string> */
    public function idsByTenant(string $hqId): array;

    /** @param list<string> $areaIds @return list<string> */
    public function activeIdsAmong(string $hqId, array $areaIds): array;

    /** Active Areas in title order, keyed by id, for building a scope picker without a lookup per row. @return array<string, AreaRecord> */
    public function activeByTitleKeyedById(string $hqId): array;

    /** @param list<string> $areaIds @return Collection<string, AreaRecord> */
    public function byIdsKeyedByAreaId(string $hqId, array $areaIds): Collection;

    /** Locks both ends of a prospective edge in a stable order so two concurrent edits cannot deadlock. @param list<string> $areaIds @return Collection<string, AreaRecord> */
    public function lockByIdsKeyedByAreaId(string $hqId, array $areaIds): Collection;

    /** @param list<string> $visibleIds @return LengthAwarePaginator<AreaRecord> */
    public function search(string $hqId, array $visibleIds, NetworkFiltersDto $filters): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): AreaRecord;

    /** @param array<string, mixed> $changes */
    public function apply(AreaRecord $area, array $changes): void;
}
