<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogItems;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

/** The whole filtered catalog, by code, each item with its category and industries. */
final readonly class ListCatalogItemsResult
{
    /**
     * @param  Collection<int, CatalogItemRecord>  $items
     */
    public function __construct(
        public Collection $items,
    ) {}
}
