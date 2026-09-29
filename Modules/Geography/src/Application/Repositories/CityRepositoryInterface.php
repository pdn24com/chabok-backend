<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Dto\GeographySearchDto;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;

interface CityRepositoryInterface
{
    /** Loads the City together with its Province, because every caller reads the Province name. */
    public function findWithProvince(string $cityId): ?CityRecord;

    public function findActive(string $cityId): ?CityRecord;

    /** @param list<string> $cityIds @return list<string> */
    public function activeIds(array $cityIds): array;

    /** @param list<string> $cityIds @return list<string> */
    public function idsAmong(array $cityIds): array;

    public function find(string $cityId): ?CityRecord;

    /** @return LengthAwarePaginator<CityRecord> */
    public function search(GeographySearchDto $filters, ?string $normalizedTerm): LengthAwarePaginator;

    /**
     * Resolves active Cities by canonical id or by normalized name in one read, so callers holding a mix of
     * both kinds of reference do not fall back to a lookup per member.
     *
     * @param  list<string>  $cityIds
     * @param  list<string>  $normalizedNames
     * @return Collection<int, CityRecord>
     */
    public function activeByIdsOrNormalizedNames(array $cityIds, array $normalizedNames): Collection;
}
