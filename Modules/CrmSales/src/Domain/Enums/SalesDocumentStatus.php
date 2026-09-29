<?php

declare(strict_types=1);

namespace Modules\CrmSales\Domain\Enums;

/** REVIEW is carried over from the previous model; its executable meaning is still undecided. */
enum SalesDocumentStatus: string
{
    case DRAFT = 'DRAFT';
    case REVIEW = 'REVIEW';
    case ISSUED = 'ISSUED';
    case ACCEPTED = 'ACCEPTED';
    case CANCELLED = 'CANCELLED';
}
