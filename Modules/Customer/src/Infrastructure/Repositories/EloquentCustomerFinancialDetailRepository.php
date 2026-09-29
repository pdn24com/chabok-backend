<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Modules\Customer\Application\Repositories\CustomerFinancialDetailRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerFinancialDetailRecord;

final class EloquentCustomerFinancialDetailRepository implements CustomerFinancialDetailRepositoryInterface
{
    public function findForCustomer(string $hqId, string $customerId): ?CustomerFinancialDetailRecord
    {
        return CustomerFinancialDetailRecord::query()->where(['hq_id' => $hqId, 'customer_id' => $customerId])->first();
    }

    public function save(array $attributes, array $changes): void
    {
        CustomerFinancialDetailRecord::query()->upsert([$attributes], ['hq_id', 'customer_id'], $changes);
    }
}
