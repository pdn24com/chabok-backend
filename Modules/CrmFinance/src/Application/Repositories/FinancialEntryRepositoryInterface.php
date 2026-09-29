<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialEntryRecord;

interface FinancialEntryRepositoryInterface
{
    /**
     * The financial entries of one customer, newest effective date first, each carrying how much of it
     * has already been allocated. Optionally narrowed to a single kind.
     *
     * @return Collection<int, FinancialEntryRecord>
     */
    public function listForCustomer(string $hqId, string $customerId, ?FinancialEntryKind $kind = null): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): FinancialEntryRecord;

    public function findForCustomer(string $hqId, string $customerId, string $entryId): ?FinancialEntryRecord;

    public function findForTenant(string $hqId, string $entryId): ?FinancialEntryRecord;

    /** Reads the entry for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $entryId): ?FinancialEntryRecord;

    /** True when some entry already reverses the one named, so a second reversal is refused. */
    public function alreadyReversed(string $hqId, string $entryId): bool;
}
