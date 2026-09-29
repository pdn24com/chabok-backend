<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerFinancialEntry;

use Modules\CrmFinance\Application\Dto\FinancialEntryDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerFinancialEntryCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public FinancialEntryDraftDto $input,
    ) {}
}
