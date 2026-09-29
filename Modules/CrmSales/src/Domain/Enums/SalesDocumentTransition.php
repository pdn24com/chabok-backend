<?php

declare(strict_types=1);

namespace Modules\CrmSales\Domain\Enums;

/** What an operator can do to the revision a sales document currently shows. */
enum SalesDocumentTransition: string
{
    case ISSUE = 'ISSUE';
    case ACCEPT = 'ACCEPT';
    case CANCEL = 'CANCEL';
}
