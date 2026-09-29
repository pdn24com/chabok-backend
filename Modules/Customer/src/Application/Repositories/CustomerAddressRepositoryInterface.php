<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

interface CustomerAddressRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerAddressRecord;

    /**
     * Every address of one customer, default first and then in the order they were added. A customer owns
     * a handful of addresses, so the whole book is read at once rather than a page of it.
     *
     * @return Collection<int, CustomerAddressRecord>
     */
    public function listForCustomer(string $hqId, string $customerId): Collection;

    public function findForCustomer(string $hqId, string $customerId, string $addressId): ?CustomerAddressRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForCustomer(string $hqId, string $customerId, string $addressId): ?CustomerAddressRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $addressId, array $attributes): void;

    /** Drops the default flag from every address of the customer except the one named. */
    public function clearDefaults(string $hqId, string $customerId, ?string $exceptAddressId = null): void;

    public function existsForCustomer(string $hqId, string $customerId): bool;
}
