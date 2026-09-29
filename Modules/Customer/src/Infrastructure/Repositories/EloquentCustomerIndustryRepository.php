<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Modules\Customer\Application\Repositories\CustomerIndustryRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerIndustryRecord;

final class EloquentCustomerIndustryRepository implements CustomerIndustryRepositoryInterface
{
    public function create(array $attributes): CustomerIndustryRecord
    {
        return CustomerIndustryRecord::query()->forceCreate($attributes);
    }

    public function linkExists(string $hqId, string $customerId, string $industryId): bool
    {
        return CustomerIndustryRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId, 'industry_id' => $industryId])
            ->exists();
    }

    public function clearPrimary(string $hqId, string $customerId): void
    {
        CustomerIndustryRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId, 'is_primary' => true])
            ->update(['is_primary' => false]);
    }

    public function markPrimary(string $hqId, string $customerId, string $industryId): void
    {
        CustomerIndustryRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId, 'industry_id' => $industryId])
            ->update(['is_primary' => true]);
    }
}
