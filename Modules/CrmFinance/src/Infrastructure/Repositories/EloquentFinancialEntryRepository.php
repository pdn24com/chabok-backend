<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Application\Repositories\FinancialEntryRepositoryInterface;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialEntryRecord;

final class EloquentFinancialEntryRepository implements FinancialEntryRepositoryInterface
{
    public function listForCustomer(string $hqId, string $customerId, ?FinancialEntryKind $kind = null): Collection
    {
        // The allocated total belongs to every row, so it is read in the same statement rather than per row.
        $query = $this->ofCustomer($hqId, $customerId)->withSum('allocations as allocated_total', 'amount');
        if ($kind !== null) {
            $query->where('kind', $kind->value);
        }

        return $query->orderByDesc('effective_on')->orderByDesc('id')->get();
    }

    public function create(array $attributes): FinancialEntryRecord
    {
        return FinancialEntryRecord::query()->forceCreate($attributes);
    }

    public function findForCustomer(string $hqId, string $customerId, string $entryId): ?FinancialEntryRecord
    {
        return $this->ofCustomer($hqId, $customerId)
            ->withSum('allocations as allocated_total', 'amount')
            ->where('financial_entry_id', $entryId)
            ->first();
    }

    public function findForTenant(string $hqId, string $entryId): ?FinancialEntryRecord
    {
        return $this->ofTenant($hqId, $entryId)->first();
    }

    public function lockForTenant(string $hqId, string $entryId): ?FinancialEntryRecord
    {
        return $this->ofTenant($hqId, $entryId)->lockForUpdate()->first();
    }

    public function alreadyReversed(string $hqId, string $entryId): bool
    {
        return FinancialEntryRecord::query()->where(['hq_id' => $hqId, 'reverses_id' => $entryId])->exists();
    }

    /** @return Builder<FinancialEntryRecord> */
    private function ofCustomer(string $hqId, string $customerId): Builder
    {
        return FinancialEntryRecord::query()->where(['hq_id' => $hqId, 'customer_id' => $customerId]);
    }

    /** @return Builder<FinancialEntryRecord> */
    private function ofTenant(string $hqId, string $entryId): Builder
    {
        return FinancialEntryRecord::query()->where(['hq_id' => $hqId, 'financial_entry_id' => $entryId]);
    }
}
