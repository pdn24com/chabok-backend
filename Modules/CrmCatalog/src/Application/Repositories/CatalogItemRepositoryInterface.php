<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Application\Dto\CatalogItemFiltersDto;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

interface CatalogItemRepositoryInterface
{
    public function create(array $attributes): CatalogItemRecord;

    /**
     * The whole filtered set, by code, with the category and the industries of every row resolved in
     * batched queries.
     *
     * @return Collection<int, CatalogItemRecord>
     */
    public function listForTenant(string $hqId, CatalogItemFiltersDto $filters): Collection;

    /** One item with its category, persona, sales model and industries loaded. */
    public function findForTenant(string $hqId, string $catalogItemId): ?CatalogItemRecord;

    /** The item's row, locked for the rest of the transaction. */
    public function lockForTenant(string $hqId, string $catalogItemId): ?CatalogItemRecord;

    /** Whether another item of the tenant already uses the code. */
    public function codeTaken(string $hqId, string $code, ?string $exceptCatalogItemId = null): bool;

    public function update(string $hqId, string $catalogItemId, array $attributes): void;
}
