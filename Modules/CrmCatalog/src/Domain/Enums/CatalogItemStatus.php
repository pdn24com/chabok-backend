<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Domain\Enums;

/** Where a catalog item stands in the offer: sold now, paused, or retired from the catalog. */
enum CatalogItemStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
