<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum PricingPurpose: string
{
    case Sales = 'SALES';
    case Purchase = 'PURCHASE';
    case Commission = 'COMMISSION';
    case InternalTransfer = 'INTERNAL_TRANSFER';
}
