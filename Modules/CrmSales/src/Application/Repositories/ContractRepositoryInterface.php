<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmSales\Infrastructure\Persistence\Models\ContractRecord;

interface ContractRepositoryInterface
{
    /**
     * Every contract of one customer, newest first.
     *
     * @return Collection<int, ContractRecord>
     */
    public function listForCustomer(string $hqId, string $customerId): Collection;

    public function findForTenant(string $hqId, string $contractId): ?ContractRecord;

    public function existsForTenant(string $hqId, string $contractId): bool;

    /** True when the contract exists in the tenant and belongs to this customer. */
    public function existsForCustomer(string $hqId, string $customerId, string $contractId): bool;

    /** True when the customer already holds a contract under exactly this reference number. */
    public function referenceTaken(string $hqId, string $customerId, string $referenceNo): bool;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ContractRecord;
}
