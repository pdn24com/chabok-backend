<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Dto\ConsignmentDriverOptionsDto;
use Modules\Consignment\Application\Dto\ConsignmentFiltersDto;
use Modules\Consignment\Application\Dto\ConsignmentStatusGroupTotalsDto;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;

interface ConsignmentRepositoryInterface
{
    /** @param list<string> $nodeIds @return LengthAwarePaginator<ConsignmentRecord> */
    public function paginateVisible(string $hqId, array $nodeIds, ConsignmentFiltersDto $filters): LengthAwarePaginator;

    /** @param list<string> $nodeIds */
    public function statusGroupTotals(string $hqId, array $nodeIds, ConsignmentFiltersDto $filters): ConsignmentStatusGroupTotalsDto;

    /** @param list<string> $nodeIds */
    public function driverOptions(string $hqId, array $nodeIds): ConsignmentDriverOptionsDto;

    /** Ids of every Consignment visible at the given Nodes, in id order. @param list<string> $nodeIds @return list<string> */
    public function visibleIds(string $hqId, array $nodeIds): array;

    /** Loads one visible Consignment with every relation its detail response renders. @param list<string> $nodeIds */
    public function findVisibleDetail(string $hqId, array $nodeIds, string $consignmentId, bool $includeAudit): ?ConsignmentRecord;

    /** @param list<string> $nodeIds */
    public function lockVisible(string $hqId, array $nodeIds, string $consignmentId): ?ConsignmentRecord;

    /** Locks the given Consignments in id order, so concurrent projections cannot deadlock. @param list<string> $consignmentIds @return Collection<int, ConsignmentRecord> */
    public function lockByIds(string $hqId, array $consignmentIds): Collection;

    /**
     * Locks the Consignments that own the given Parcels, in id order, before their Parcels are locked.
     * Taking parents first is what keeps concurrent manifest runs from deadlocking.
     *
     * @param  list<string>  $parcelIds
     */
    public function lockParentsOfParcels(string $hqId, array $parcelIds): void;

    /** Records the driver now carrying out the pickup of the given Consignments. @param list<string> $consignmentIds */
    public function assignPickupDriver(?string $hqId, array $consignmentIds, ?string $driverId): void;

    public function lockAtPickupNode(?string $hqId, string $consignmentId, string $nodeId): ?ConsignmentRecord;

    public function findConfirmedAtPickupNode(?string $hqId, string $consignmentId, string $nodeId): ?ConsignmentRecord;

    /** Replaces the aggregate columns of already-locked rows. @param list<array<string, mixed>> $rows */
    public function upsertAggregates(array $rows): void;
}
