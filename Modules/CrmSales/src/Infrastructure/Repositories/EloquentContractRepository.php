<?php

declare(strict_types=1);

namespace Modules\CrmSales\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmSales\Application\Repositories\ContractRepositoryInterface;
use Modules\CrmSales\Infrastructure\Persistence\Models\ContractRecord;

final class EloquentContractRepository implements ContractRepositoryInterface
{
    public function listForCustomer(string $hqId, string $customerId): Collection
    {
        return ContractRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function findForTenant(string $hqId, string $contractId): ?ContractRecord
    {
        return ContractRecord::query()->where(['hq_id' => $hqId, 'contract_id' => $contractId])->first();
    }

    public function existsForTenant(string $hqId, string $contractId): bool
    {
        return ContractRecord::query()->where(['hq_id' => $hqId, 'contract_id' => $contractId])->exists();
    }

    public function existsForCustomer(string $hqId, string $customerId, string $contractId): bool
    {
        return ContractRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId, 'contract_id' => $contractId])
            ->exists();
    }

    public function referenceTaken(string $hqId, string $customerId, string $referenceNo): bool
    {
        return ContractRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId, 'reference_no' => $referenceNo])
            ->exists();
    }

    public function create(array $attributes): ContractRecord
    {
        return ContractRecord::query()->forceCreate($attributes);
    }
}
