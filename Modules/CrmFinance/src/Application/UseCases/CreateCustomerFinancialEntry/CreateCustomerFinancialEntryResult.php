<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerFinancialEntry;

use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialEntryRecord;

/** The stored financial entry. */
final readonly class CreateCustomerFinancialEntryResult
{
    public function __construct(
        public FinancialEntryRecord $entry,
    ) {}
}
