<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface ManifestRepositoryInterface
{
    public function findAtNode(?string $hqId, string $nodeId, string $manifestId): ?ManifestRecord;

    public function lockAtNode(?string $hqId, string $nodeId, string $manifestId): ?ManifestRecord;

    /** @return LengthAwarePaginator<ManifestRecord> */
    public function paginateAtNode(?string $hqId, string $nodeId, ManifestFiltersDto $filters): LengthAwarePaginator;

    /** @param array<string, mixed> $changes */
    public function update(string $manifestId, array $changes): void;

    /** Closed outbound transfers at a Node whose successful rows still sit in outbound custody. @return Collection<int, ManifestRecord> */
    public function closedOutboundTransfers(string $hqId, string $nodeId): Collection;

    /** Closed linehaul departures bound for a Node, with the route legs a reception needs. @return Collection<int, ManifestRecord> */
    public function inboundLinehaulArrivals(string $hqId, string $nodeId): Collection;

    /** Manifests that recorded an outcome for one Consignment's Parcels, with those rows loaded. @return Collection<int, ManifestRecord> */
    public function outcomesForConsignment(string $hqId, string $consignmentId): Collection;
}
