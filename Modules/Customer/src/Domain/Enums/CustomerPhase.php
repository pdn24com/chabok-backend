<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

enum CustomerPhase: string
{
    case LEAD = 'LEAD';
    case CUSTOMER = 'CUSTOMER';
}
