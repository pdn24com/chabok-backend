<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Geography\Application\Dto\GeographySearchDto;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;

interface CountryRepositoryInterface
{
    /** @return LengthAwarePaginator<CountryRecord> */
    public function search(GeographySearchDto $filters, ?string $normalizedTerm): LengthAwarePaginator;

    public function find(string $countryId): ?CountryRecord;

    public function findActiveByCode(string $countryCode): ?CountryRecord;
}
