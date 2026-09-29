<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Application\Repositories\BankAccountRepositoryInterface;
use Modules\CrmFinance\Infrastructure\Persistence\Models\BankAccountRecord;

final class EloquentBankAccountRepository implements BankAccountRepositoryInterface
{
    public function listForCustomer(string $hqId, string $customerId): Collection
    {
        return $this->ofCustomer($hqId, $customerId)->orderByDesc('is_primary')->orderBy('id')->get();
    }

    public function create(array $attributes): BankAccountRecord
    {
        return BankAccountRecord::query()->forceCreate($attributes);
    }

    public function existsForCustomer(string $hqId, string $customerId): bool
    {
        return $this->ofCustomer($hqId, $customerId)->exists();
    }

    public function clearPrimary(string $hqId, string $customerId): void
    {
        $this->ofCustomer($hqId, $customerId)->where('is_primary', true)->update(['is_primary' => false]);
    }

    /** @return Builder<BankAccountRecord> */
    private function ofCustomer(string $hqId, string $customerId): Builder
    {
        return BankAccountRecord::query()->where(['hq_id' => $hqId, 'customer_id' => $customerId]);
    }
}
