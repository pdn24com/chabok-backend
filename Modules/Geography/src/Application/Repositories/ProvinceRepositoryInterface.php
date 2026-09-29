<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Dto\GeographySearchDto;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;

interface ProvinceRepositoryInterface
{
    /** @return LengthAwarePaginator<ProvinceRecord> */
    public function search(GeographySearchDto $filters, ?string $normalizedTerm): LengthAwarePaginator;

    /** @param list<string> $provinceIds @return list<string> */
    public function activeIds(array $provinceIds): array;

    public function activeExists(string $provinceId): bool;

    /** @param list<string> $provinceIds @return list<string> */
    public function idsAmong(array $provinceIds): array;
}
