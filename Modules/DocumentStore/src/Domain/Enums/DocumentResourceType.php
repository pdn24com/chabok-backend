<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Domain\Enums;

/**
 * The registry of records a document may be attached to. A link is polymorphic, so the type is what
 * decides which table the resource ID is read against, and only the kinds named here are accepted:
 * an open-ended column would let a document point at a row nobody can resolve.
 *
 * The prototype also foresees SALES_DOCUMENT and CATALOG_ITEM. Each arrives here together
 * with the read model of the module that owns it, because a link that cannot be checked is not a link.
 */
enum DocumentResourceType: string
{
    case CUSTOMER = 'CUSTOMER';
    case OPPORTUNITY = 'OPPORTUNITY';
    case CONTRACT = 'CONTRACT';
}
