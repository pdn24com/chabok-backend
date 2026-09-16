<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Foundation\Application\Data\Page;
use Modules\Geography\Application\Repositories\GeographyRepository;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;

final class EloquentGeographyRepository implements GeographyRepository
{
    public function provinces(array $filters, ?string $normalizedSearch): Page
    {
        $query = ProvinceRecord::query()->orderByRaw('CAST(legacy_province_code AS UNSIGNED)')->where('is_active', (bool) ($filters['active'] ?? true));
        if ($normalizedSearch !== null) {
            $query->where('normalized_name', 'like', '%' . $normalizedSearch . '%');
        }
        return $this->page($query->toBase()->paginate((int) ($filters['per_page'] ?? 50), page: (int) ($filters['page'] ?? 1)));
    }

    public function cities(array $filters, ?string $normalizedSearch): Page
    {
        $query = CityRecord::query()->from('cities as c')->join('provinces as p', 'p.province_id', '=', 'c.province_id')->select([
            'c.city_id',
            'c.legacy_city_code',
            'c.name_fa',
            'c.normalized_name',
            'c.is_active',
            'p.province_id',
            'p.legacy_province_code',
            'p.name_fa as province_name_fa',
        ])->where('c.is_active', (bool) ($filters['active'] ?? true))->orderBy('c.normalized_name')->orderByRaw('CAST(c.legacy_city_code AS UNSIGNED)');
        if (($filters['province_id'] ?? null) !== null) {
            $query->where('c.province_id', $filters['province_id']);
        }
        if (($filters['province_code'] ?? null) !== null) {
            $query->where('p.legacy_province_code', $filters['province_code']);
        }
        if ($normalizedSearch !== null) {
            $query->where('c.normalized_name', 'like', '%' . $normalizedSearch . '%');
        }
        return $this->page($query->toBase()->paginate((int) ($filters['per_page'] ?? 25), page: (int) ($filters['page'] ?? 1)));
    }

    public function city(string $cityId): ?\stdClass
    {
        return CityRecord::query()->from('cities as c')->join('provinces as p', 'p.province_id', '=', 'c.province_id')->where('c.city_id', $cityId)->select([
            'c.city_id',
            'c.legacy_city_code',
            'c.name_fa',
            'c.normalized_name',
            'c.is_active',
            'p.province_id',
            'p.legacy_province_code',
            'p.name_fa as province_name_fa',
            'p.is_active as province_active',
        ])->toBase()->first();
    }

    private function page(LengthAwarePaginator $result): Page
    {
        return new Page($result->items(), $result->currentPage(), $result->perPage(), $result->total());
    }
}
