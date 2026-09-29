<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerFinancialEntries;

use Modules\CrmFinance\Application\Dto\FinancialEntryFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerFinancialEntriesCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public FinancialEntryFiltersDto $filters,
    ) {}
}
