<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Customer\Application\Dto\CustomerListFiltersDto;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** The crm_customers row itself; everything hanging off a customer has a repository of its own. */
interface CustomerRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerRecord;

    /** Loads one customer with everything the detail view shows about the record itself. */
    public function findDetailForTenant(string $hqId, string $customerId): ?CustomerRecord;

    /** Loads the editable profile field set, with the assignee and the primary industry it names. */
    public function findProfileForTenant(string $hqId, string $customerId): ?CustomerRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $customerId): ?CustomerRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $customerId, array $attributes): void;

    /** True when the tenant owns a customer under this ID, without reading anything hanging off it. */
    public function existsForTenant(string $hqId, string $customerId): bool;

    public function isCompany(string $hqId, string $customerId): bool;

    /** True while the record has been promoted out of the lead phase into a customer of the tenant. */
    public function isInCustomerPhase(string $hqId, string $customerId): bool;

    /**
     * The display names of the given customers, keyed by ID, so a caller naming a page of rows reads
     * them in one query instead of one per row.
     *
     * @param  list<string>  $customerIds
     * @return array<string, string|null>
     */
    public function displayNamesFor(string $hqId, array $customerIds): array;

    /** @return LengthAwarePaginator<CustomerRecord> */
    public function paginateForTenant(string $hqId, CustomerListFiltersDto $filters): LengthAwarePaginator;
}
