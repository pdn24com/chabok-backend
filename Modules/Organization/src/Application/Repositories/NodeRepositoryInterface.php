<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Application\Dto\NetworkFiltersDto;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

interface NodeRepositoryInterface
{
    public function findByTenant(string $hqId, string $nodeId): ?NodeRecord;

    public function findActive(string $hqId, string $nodeId): ?NodeRecord;

    public function activeExists(string $hqId, string $nodeId): bool;

    public function codeExists(string $hqId, string $code): bool;

    public function lockByTenant(string $hqId, string $nodeId): ?NodeRecord;

    /** The owning tenant of a Node that has not yet been scoped to one, used before any tenant filter applies. */
    public function tenantOf(string $nodeId): ?string;

    /** @param list<string> $nodeIds @return list<string> */
    public function activeIdsAmong(string $hqId, array $nodeIds): array;

    /** @param list<string> $nodeIds @return list<string> */
    public function idsAmong(string $hqId, array $nodeIds): array;

    /**
     * Active Node ids reachable from the given Area and Node scopes, or every active Node when the
     * caller holds tenant-wide access.
     *
     * @param  list<string>  $areaIds
     * @param  list<string>  $nodeIds
     * @return list<string>
     */
    public function activeIdsInScopes(string $hqId, array $areaIds, array $nodeIds, bool $tenantWide): array;

    public function hasActiveInArea(string $hqId, string $areaId): bool;

    /** @param list<string> $nodeIds @return list<NodeRecord> */
    public function directoryEntries(string $hqId, array $nodeIds, bool $activeOnly): array;

    /** @param list<string> $visibleIds @return LengthAwarePaginator<NodeRecord> */
    public function search(string $hqId, array $visibleIds, NetworkFiltersDto $filters): LengthAwarePaginator;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): NodeRecord;

    /** @param array<string, mixed> $changes */
    public function apply(NodeRecord $node, array $changes): void;
}
