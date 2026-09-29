<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Repositories;

use Modules\CrmFinance\Application\Repositories\FinancialAllocationRepositoryInterface;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialAllocationRecord;

final class EloquentFinancialAllocationRepository implements FinancialAllocationRepositoryInterface
{
    public function create(array $attributes): FinancialAllocationRecord
    {
        return FinancialAllocationRecord::query()->forceCreate($attributes);
    }

    public function allocatedTotalForEntry(string $hqId, string $entryId): int
    {
        return (int) FinancialAllocationRecord::query()
            ->where(['hq_id' => $hqId, 'receipt_entry_id' => $entryId])
            ->sum('amount');
    }
}
