<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerBankAccount;

use Modules\CrmFinance\Infrastructure\Persistence\Models\BankAccountRecord;

/** The stored bank account. */
final readonly class CreateCustomerBankAccountResult
{
    public function __construct(
        public BankAccountRecord $bankAccount,
    ) {}
}
