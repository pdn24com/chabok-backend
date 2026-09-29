<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Manifest\Application\Dto\ManifestCandidateScopeDto;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;

interface ManifestCandidateRepositoryInterface
{
    /** @return LengthAwarePaginator<ParcelRecord> */
    public function paginateCandidates(string $hqId, ManifestFiltersDto $filters, ManifestCandidateScopeDto $scope): LengthAwarePaginator;

    /** Every candidate in scope, for callers that size the result themselves. @return Collection<int, ParcelRecord> */
    public function candidates(string $hqId, ManifestFiltersDto $filters, ManifestCandidateScopeDto $scope): Collection;

    /** @return list<string> */
    public function visibleParcelIds(string $hqId, string $nodeId): array;

    /**
     * Locks the Parcels a caller named by Parcel or Consignment number, restricted to what is visible at
     * the Node, so an add cannot pull in a Parcel the caller cannot see.
     *
     * @param  list<string>  $identifiers
     * @return Collection<int, ParcelRecord>
     */
    public function lockVisibleByIdentifiers(string $hqId, string $nodeId, array $identifiers): Collection;
}
