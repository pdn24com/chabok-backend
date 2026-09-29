<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogSalesModels;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogSalesModelRecord;

/** The tenant's catalog sales models, by display order. */
final readonly class ListCatalogSalesModelsResult
{
    /**
     * @param  Collection<int, CatalogSalesModelRecord>  $salesModels
     */
    public function __construct(
        public Collection $salesModels,
    ) {}
}
