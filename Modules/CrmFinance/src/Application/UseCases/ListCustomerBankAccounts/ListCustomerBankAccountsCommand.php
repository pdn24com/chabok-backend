<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerBankAccounts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerBankAccountsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
