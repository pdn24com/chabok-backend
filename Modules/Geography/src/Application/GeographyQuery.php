<?php

declare(strict_types=1);

namespace Modules\Geography\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Geography\Domain\PersianSearchNormalizer;

final readonly class GeographyQuery
{
    public function __construct(private PersianSearchNormalizer $normalizer) {}

    /** @param array<string, mixed> $filters */
    public function provinces(array $filters): LengthAwarePaginator
    {
        $query = DB::table('provinces')->orderByRaw('CAST(legacy_province_code AS UNSIGNED)');
        $query->where('is_active', (bool) ($filters['active'] ?? true));
        if (($filters['search'] ?? null) !== null) {
            $query->where('normalized_name', 'like', '%'.$this->normalizer->normalize((string) $filters['search']).'%');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 50), page: (int) ($filters['page'] ?? 1));
    }

    /** @param array<string, mixed> $filters */
    public function cities(array $filters): LengthAwarePaginator
    {
        $query = DB::table('cities as c')
            ->join('provinces as p', 'p.province_id', '=', 'c.province_id')
            ->select([
                'c.city_id', 'c.legacy_city_code', 'c.name_fa', 'c.normalized_name', 'c.is_active',
                'p.province_id', 'p.legacy_province_code', 'p.name_fa as province_name_fa',
            ])
            ->where('c.is_active', (bool) ($filters['active'] ?? true))
            ->orderBy('c.normalized_name')->orderByRaw('CAST(c.legacy_city_code AS UNSIGNED)');
        if (($filters['province_id'] ?? null) !== null) {
            $query->where('c.province_id', $filters['province_id']);
        }
        if (($filters['province_code'] ?? null) !== null) {
            $query->where('p.legacy_province_code', $filters['province_code']);
        }
        if (($filters['search'] ?? null) !== null) {
            $query->where('c.normalized_name', 'like', '%'.$this->normalizer->normalize((string) $filters['search']).'%');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 25), page: (int) ($filters['page'] ?? 1));
    }

    /** @return array<string, mixed> */
    public function city(string $cityId, bool $activeOnly = true): array
    {
        $query = DB::table('cities as c')
            ->join('provinces as p', 'p.province_id', '=', 'c.province_id')
            ->where('c.city_id', $cityId)
            ->select([
                'c.city_id', 'c.legacy_city_code', 'c.name_fa', 'c.normalized_name', 'c.is_active',
                'p.province_id', 'p.legacy_province_code', 'p.name_fa as province_name_fa',
            ]);
        if ($activeOnly) {
            $query->where('c.is_active', true)->where('p.is_active', true);
        }
        $row = $query->first();
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }

        return $this->cityResource((array) $row);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public function provinceResource(array $row): array
    {
        return [
            'province_id' => $row['province_id'],
            'legacy_province_code' => (string) $row['legacy_province_code'],
            'name_fa' => $row['name_fa'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'is_active' => (bool) $row['is_active'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public function cityResource(array $row): array
    {
        return [
            'city_id' => $row['city_id'],
            'legacy_city_code' => (string) $row['legacy_city_code'],
            'name_fa' => $row['name_fa'],
            'display_name' => sprintf('%s — %s (کد %s)', $row['name_fa'], $row['province_name_fa'], $row['legacy_city_code']),
            'is_active' => (bool) $row['is_active'],
            'province' => [
                'province_id' => $row['province_id'],
                'legacy_province_code' => (string) $row['legacy_province_code'],
                'name_fa' => $row['province_name_fa'],
            ],
        ];
    }
}
