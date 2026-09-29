<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

interface ManifestParcelRepositoryInterface
{
    /**
     * Inserts a batch and reports whether the unique active-slot index rejected it, so the caller can
     * retry row by row and keep each Parcel's own outcome. Any other database error still escapes.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertUnlessSlotTaken(array $rows): bool;

    /** Saves one row, reporting an active-slot conflict the same way. */
    public function saveUnlessSlotTaken(ManifestParcelRecord $row): bool;

    public function save(ManifestParcelRecord $row): void;

    /** Writes back only the rows the caller actually changed, then re-syncs them. @param Collection<int, ManifestParcelRecord> $rows */
    public function saveDirty(Collection $rows): void;

    /** @param list<string> $statuses @return Collection<int, ManifestParcelRecord> */
    public function lockRowsWithStatus(?string $hqId, string $manifestId, array $statuses): Collection;

    /** Same read without a tenant filter, for a caller that already resolved the Manifest's tenant. @param list<string> $statuses @return Collection<int, ManifestParcelRecord> */
    public function lockManifestRowsWithStatus(string $manifestId, array $statuses): Collection;

    /** @return Collection<int, ManifestParcelRecord> */
    public function lockAllRows(?string $hqId, string $manifestId): Collection;

    /** Successful rows of a source Manifest, keyed by Parcel, which is the evidence a reception carries forward. @param list<string> $parcelIds @return Collection<string, ManifestParcelRecord> */
    public function succeededSourceRows(?string $hqId, ?string $sourceManifestId, array $parcelIds): Collection;

    /** @return Collection<int, ManifestParcelRecord> */
    public function rowsWithConsignment(?string $hqId, string $manifestId): Collection;

    public function firstParcelOf(string $manifestId): ?object;

    /** @return list<string> */
    public function parcelIds(string $manifestId): array;

    public function hasRows(string $manifestId): bool;

    /** Row counts per outcome for one Manifest. @return array<string, int> */
    public function statusTotals(string $manifestId): array;

    /** Row counts per outcome for several Manifests, grouped by Manifest. @param list<string> $manifestIds @return SupportCollection<string, SupportCollection<int, object>> */
    public function statusTotalsByManifest(?string $hqId, array $manifestIds): SupportCollection;
}
