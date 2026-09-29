<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Modules\Customer\Application\Repositories\CustomerExtendedDetailRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerExtendedDetailRecord;

final class EloquentCustomerExtendedDetailRepository implements CustomerExtendedDetailRepositoryInterface
{
    public function findForCustomer(string $hqId, string $customerId): ?CustomerExtendedDetailRecord
    {
        return CustomerExtendedDetailRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId])
            ->with(['evaluatedBy' => fn ($evaluator) => $evaluator->select(['id', 'display_name'])])
            ->first();
    }

    public function save(array $attributes, array $changes): void
    {
        CustomerExtendedDetailRecord::query()->upsert([$attributes], ['hq_id', 'customer_id'], $changes);
    }
}
