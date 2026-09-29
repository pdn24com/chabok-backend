<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum QuoteStatus: string
{
    case Offered = 'OFFERED';
    case Accepted = 'ACCEPTED';
    case Expired = 'EXPIRED';
    case Superseded = 'SUPERSEDED';
    case Rejected = 'REJECTED';
    case Void = 'VOID';
}
