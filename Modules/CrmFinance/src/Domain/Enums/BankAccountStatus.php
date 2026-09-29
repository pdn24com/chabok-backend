<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Domain\Enums;

/** Whether a stored bank account is still usable. Only an active account can be the primary one. */
enum BankAccountStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
