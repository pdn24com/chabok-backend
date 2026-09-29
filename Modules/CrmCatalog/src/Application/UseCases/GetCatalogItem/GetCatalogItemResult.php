<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\GetCatalogItem;

use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

/** One catalog item's identity card. */
final readonly class GetCatalogItemResult
{
    public function __construct(
        public CatalogItemRecord $item,
    ) {}
}
