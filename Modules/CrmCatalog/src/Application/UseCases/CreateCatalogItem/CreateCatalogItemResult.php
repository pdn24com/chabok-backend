<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\CreateCatalogItem;

use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

/** The catalog item as it was written, with its references named. */
final readonly class CreateCatalogItemResult
{
    public function __construct(
        public CatalogItemRecord $item,
    ) {}
}
