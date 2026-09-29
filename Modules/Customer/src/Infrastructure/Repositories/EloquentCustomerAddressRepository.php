<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

final class EloquentCustomerAddressRepository implements CustomerAddressRepositoryInterface
{
    public function create(array $attributes): CustomerAddressRecord
    {
        return CustomerAddressRecord::query()->forceCreate($attributes);
    }

    public function listForCustomer(string $hqId, string $customerId): Collection
    {
        return $this->ofCustomer($hqId, $customerId)
            ->with($this->placeNames())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    public function findForCustomer(string $hqId, string $customerId, string $addressId): ?CustomerAddressRecord
    {
        return $this->ofCustomer($hqId, $customerId)
            ->with($this->placeNames())
            ->where('customer_address_id', $addressId)
            ->first();
    }

    public function lockForCustomer(string $hqId, string $customerId, string $addressId): ?CustomerAddressRecord
    {
        return $this->ofCustomer($hqId, $customerId)->where('customer_address_id', $addressId)->lockForUpdate()->first();
    }

    public function update(string $hqId, string $addressId, array $attributes): void
    {
        CustomerAddressRecord::query()->where(['hq_id' => $hqId, 'customer_address_id' => $addressId])->update($attributes);
    }

    public function clearDefaults(string $hqId, string $customerId, ?string $exceptAddressId = null): void
    {
        $query = $this->ofCustomer($hqId, $customerId)->where('is_default', true);
        if ($exceptAddressId !== null) {
            $query->whereKeyNot($exceptAddressId);
        }
        $query->update(['is_default' => false]);
    }

    public function existsForCustomer(string $hqId, string $customerId): bool
    {
        return $this->ofCustomer($hqId, $customerId)->exists();
    }

    /** @return Builder<CustomerAddressRecord> */
    private function ofCustomer(string $hqId, string $customerId): Builder
    {
        return CustomerAddressRecord::query()->where(['hq_id' => $hqId, 'customer_id' => $customerId]);
    }

    /** The reference names the address views print beside the country code and the two canonical IDs. */
    private function placeNames(): array
    {
        return [
            // The country is matched on its code, so the code has to come back with the row.
            'country' => fn ($country) => $country->select(['id', 'country_code', 'name_fa']),
            'province' => fn ($province) => $province->select(['id', 'name_fa']),
            'city' => fn ($city) => $city->select(['id', 'name_fa']),
        ];
    }
}
