<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Repositories;

use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialAllocationRecord;

interface FinancialAllocationRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): FinancialAllocationRecord;

    /** How much of one receipt is already set against invoices. Zero when nothing was allocated yet. */
    public function allocatedTotalForEntry(string $hqId, string $entryId): int;
}
