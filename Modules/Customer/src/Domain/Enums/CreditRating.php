<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

/** The manual credit grade of a customer. There is no lookup table behind it and no scoring engine. */
enum CreditRating: string
{
    case LOW_RISK = 'LOW_RISK';
    case MEDIUM_RISK = 'MEDIUM_RISK';
    case HIGH_RISK = 'HIGH_RISK';
}
