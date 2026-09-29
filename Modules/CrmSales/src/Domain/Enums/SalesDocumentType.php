<?php

declare(strict_types=1);

namespace Modules\CrmSales\Domain\Enums;

/** What a sales document claims to be; the financial invoice is a separate concern and not one of these. */
enum SalesDocumentType: string
{
    case ESTIMATE = 'ESTIMATE';
    case PROPOSAL = 'PROPOSAL';
    case PROFORMA = 'PROFORMA';
}
