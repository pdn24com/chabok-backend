<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogCategories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogCategoryRecord;

/** The tenant's catalog categories, by display order. */
final readonly class ListCatalogCategoriesResult
{
    /**
     * @param  Collection<int, CatalogCategoryRecord>  $categories
     */
    public function __construct(
        public Collection $categories,
    ) {}
}
