<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Domain\Enums;

/** Whether a catalog item is something delivered as a physical good or performed as a service. */
enum CatalogItemKind: string
{
    case GOOD = 'GOOD';
    case SERVICE = 'SERVICE';
}
