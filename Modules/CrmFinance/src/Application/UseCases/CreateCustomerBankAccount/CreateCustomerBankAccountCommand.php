<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerBankAccount;

use Modules\CrmFinance\Application\Dto\BankAccountDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerBankAccountCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public BankAccountDraftDto $input,
    ) {}
}
