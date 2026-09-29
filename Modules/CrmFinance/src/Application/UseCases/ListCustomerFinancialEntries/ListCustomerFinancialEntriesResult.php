<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerFinancialEntries;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialEntryRecord;

/** The financial entries of one customer, newest effective date first. */
final readonly class ListCustomerFinancialEntriesResult
{
    /**
     * @param  Collection<int, FinancialEntryRecord>  $entries
     */
    public function __construct(
        public Collection $entries,
    ) {}
}
