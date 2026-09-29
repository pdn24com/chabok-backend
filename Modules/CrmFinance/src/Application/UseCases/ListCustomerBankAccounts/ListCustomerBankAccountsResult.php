<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerBankAccounts;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Infrastructure\Persistence\Models\BankAccountRecord;

/** Every bank account of one customer, the primary one first. */
final readonly class ListCustomerBankAccountsResult
{
    /**
     * @param  Collection<int, BankAccountRecord>  $bankAccounts
     */
    public function __construct(
        public Collection $bankAccounts,
    ) {}
}
