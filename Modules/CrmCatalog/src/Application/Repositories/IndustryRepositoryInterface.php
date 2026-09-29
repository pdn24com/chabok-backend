<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CrmCatalog\Application\Dto\IndustryListFiltersDto;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\IndustryRecord;

interface IndustryRepositoryInterface
{
    /** @return LengthAwarePaginator<IndustryRecord> */
    public function paginateForTenant(string $hqId, IndustryListFiltersDto $filters): LengthAwarePaginator;

    /** Guards a customer or catalog item against pointing at a retired or foreign-tenant industry. */
    public function activeExistsInTenant(string $hqId, string $industryId): bool;
}
