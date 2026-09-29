<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Infrastructure\Persistence\Models\BankAccountRecord;

interface BankAccountRepositoryInterface
{
    /**
     * Every bank account of one customer, the primary one first. A customer owns a handful of accounts,
     * so the whole set is read at once rather than a page of it.
     *
     * @return Collection<int, BankAccountRecord>
     */
    public function listForCustomer(string $hqId, string $customerId): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): BankAccountRecord;

    public function existsForCustomer(string $hqId, string $customerId): bool;

    /**
     * Drops the primary flag from every other account of the customer. Only one active account may be
     * primary, and the database enforces it, so the old holder is cleared before the new one is written.
     */
    public function clearPrimary(string $hqId, string $customerId): void;
}
