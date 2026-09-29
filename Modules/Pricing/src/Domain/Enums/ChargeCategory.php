<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum ChargeCategory: string
{
    case BASE = 'BASE';
    case SURCHARGE = 'SURCHARGE';
    case COMMISSION = 'COMMISSION';
    case DISCOUNT = 'DISCOUNT';
    case TAX = 'TAX';
}
