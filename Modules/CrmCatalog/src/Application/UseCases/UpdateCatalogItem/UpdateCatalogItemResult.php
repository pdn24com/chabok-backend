<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\UpdateCatalogItem;

use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

/** The catalog item as the change left it. */
final readonly class UpdateCatalogItemResult
{
    public function __construct(
        public CatalogItemRecord $item,
    ) {}
}
