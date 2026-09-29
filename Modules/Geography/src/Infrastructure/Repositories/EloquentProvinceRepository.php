<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Dto\GeographySearchDto;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;

final class EloquentProvinceRepository implements ProvinceRepositoryInterface
{
    public function search(GeographySearchDto $filters, ?string $normalizedTerm): LengthAwarePaginator
    {
        $query = ProvinceRecord::query()
            ->where('is_active', $filters->active)
            ->orderByRaw('CAST(legacy_province_code AS UNSIGNED)');
        if ($normalizedTerm !== null) {
            $query->where('normalized_name', 'like', '%'.$normalizedTerm.'%');
        }

        return $query->paginate($filters->perPage, page: $filters->page);
    }

    public function activeIds(array $provinceIds): array
    {
        return ProvinceRecord::query()->where('is_active', true)->whereIn('province_id', $provinceIds)->pluck('province_id')->all();
    }

    public function idsAmong(array $provinceIds): array
    {
        return ProvinceRecord::query()->whereIn('province_id', array_unique($provinceIds))->pluck('province_id')->all();
    }

    public function activeExists(string $provinceId): bool
    {
        return ProvinceRecord::query()->where(['province_id' => $provinceId, 'is_active' => true])->exists();
    }
}
