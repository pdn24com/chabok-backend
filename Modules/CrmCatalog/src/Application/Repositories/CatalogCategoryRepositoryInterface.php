<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogCategoryRecord;

interface CatalogCategoryRepositoryInterface
{
    /**
     * The tenant's categories by display order, retired ones included unless only the active are asked for.
     *
     * @return Collection<int, CatalogCategoryRecord>
     */
    public function listForTenant(string $hqId, bool $activeOnly = false): Collection;

    /** Guards a catalog item against pointing at a retired or foreign-tenant entry. */
    public function activeExistsInTenant(string $hqId, string $id): bool;
}
