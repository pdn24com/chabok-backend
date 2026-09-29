<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Dto\GeographySearchDto;
use Modules\Geography\Application\Repositories\CountryRepositoryInterface;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;

final class EloquentCountryRepository implements CountryRepositoryInterface
{
    public function search(GeographySearchDto $filters, ?string $normalizedTerm): LengthAwarePaginator
    {
        $query = CountryRecord::query()
            ->where('is_active', $filters->active)
            ->orderBy('name_en')
            ->orderBy('country_code');
        if ($normalizedTerm !== null && $normalizedTerm !== '') {
            $query->where(fn (Builder $query) => $query
                ->whereLike('normalized_name', '%'.$normalizedTerm.'%')
                ->orWhereLike('name_en', '%'.$normalizedTerm.'%')
                ->orWhere('country_code', strtoupper($normalizedTerm))
                ->orWhere('alpha3_code', strtoupper($normalizedTerm))
                ->orWhere('numeric_code', $normalizedTerm));
        }

        return $query->paginate($filters->perPage, page: $filters->page);
    }

    public function find(string $countryId): ?CountryRecord
    {
        return CountryRecord::query()->where('country_id', $countryId)->first();
    }

    public function findActiveByCode(string $countryCode): ?CountryRecord
    {
        return CountryRecord::query()->where('country_code', $countryCode)->where('is_active', true)->first();
    }
}
