<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;

interface ParcelRepositoryInterface
{
    /** @param list<array<string, mixed>> $rows */
    public function insert(array $rows): void;

    /** @return array<string, string> */
    public function idsByNumber(string $hqId, string $consignmentId): array;

    /** Applies measurement edits to already-loaded Parcels in one statement. @param list<array<string, mixed>> $rows */
    public function upsertMeasurements(array $rows): void;

    /** Parcel counts per status for the given Consignments, aggregated in SQL. @param list<string> $consignmentIds @return Collection<int, object> */
    public function statusCounts(string $hqId, array $consignmentIds): Collection;

    /** @param list<string> $parcelIds @return Collection<string, ParcelRecord> */
    public function lockByIdsKeyedById(string $hqId, array $parcelIds): Collection;

    /** @param list<string> $parcelIds @return Collection<int, ParcelRecord> */
    public function byIds(string $hqId, array $parcelIds): Collection;

    /** The route plan a Consignment's Parcels are already moving on, if any. */
    public function activeRoutePlanId(string $hqId, string $consignmentId): ?string;
}
