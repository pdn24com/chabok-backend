<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentCategoryRecord;

interface DocumentCategoryRepositoryInterface
{
    /**
     * The whole category tree of a tenant as a flat list carrying each node's parent, so the client
     * builds the shape it wants. A tenant keeps few categories, so there is no page to ask for.
     *
     * @return Collection<int, DocumentCategoryRecord>
     */
    public function listForTenant(string $hqId, ?bool $active = null): Collection;

    /** True when the tenant owns a usable category under this ID. */
    public function activeExistsForTenant(string $hqId, string $categoryId): bool;
}
