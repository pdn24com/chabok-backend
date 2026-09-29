<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerIndustryRecord;

interface CustomerIndustryRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerIndustryRecord;

    public function linkExists(string $hqId, string $customerId, string $industryId): bool;

    public function clearPrimary(string $hqId, string $customerId): void;

    public function markPrimary(string $hqId, string $customerId, string $industryId): void;

    /**
     * Every industry link of one customer with the industry's title, the primary one first and then in the
     * order the links were made.
     *
     * @return Collection<int, CustomerIndustryRecord>
     */
    public function listForCustomer(string $hqId, string $customerId): Collection;

    /**
     * The same links as {@see listForCustomer()}, read for update; the caller must already be inside a transaction.
     *
     * @return Collection<int, CustomerIndustryRecord>
     */
    public function lockForCustomer(string $hqId, string $customerId): Collection;

    /** @param list<string> $industryIds */
    public function deleteForCustomer(string $hqId, string $customerId, array $industryIds): void;
}
