<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Dto\GeographySearchDto;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;

final class EloquentCityRepository implements CityRepositoryInterface
{
    public function findWithProvince(string $cityId): ?CityRecord
    {
        return CityRecord::query()->with('province')->where('city_id', $cityId)->first();
    }

    public function findActive(string $cityId): ?CityRecord
    {
        return CityRecord::query()->where(['city_id' => $cityId, 'is_active' => true])->first();
    }

    public function idsAmong(array $cityIds): array
    {
        return CityRecord::query()->whereIn('city_id', array_unique($cityIds))->pluck('city_id')->all();
    }

    public function find(string $cityId): ?CityRecord
    {
        return CityRecord::query()->where('city_id', $cityId)->first();
    }

    public function activeIds(array $cityIds): array
    {
        return CityRecord::query()->where('is_active', true)->whereIn('city_id', $cityIds)->pluck('city_id')->all();
    }

    public function search(GeographySearchDto $filters, ?string $normalizedTerm): LengthAwarePaginator
    {
        $query = CityRecord::query()
            ->with('province')
            ->where('is_active', $filters->active)
            ->orderBy('normalized_name')
            ->orderByRaw('CAST(legacy_city_code AS UNSIGNED)');
        if ($filters->provinceId !== null) {
            $query->where('province_id', $filters->provinceId);
        }
        if ($filters->provinceCode !== null) {
            $query->whereRelation('province', 'legacy_province_code', $filters->provinceCode);
        }
        if ($normalizedTerm !== null) {
            $query->where('normalized_name', 'like', '%'.$normalizedTerm.'%');
        }

        return $query->paginate($filters->perPage, page: $filters->page);
    }

    public function activeByIdsOrNormalizedNames(array $cityIds, array $normalizedNames): Collection
    {
        return CityRecord::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereIn('city_id', $cityIds)->orWhereIn('normalized_name', $normalizedNames))->get();
    }
}
