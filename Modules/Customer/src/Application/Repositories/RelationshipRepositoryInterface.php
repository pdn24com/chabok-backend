<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\RelationshipRecord;

/** The crm_relationships row: a person holding a role at a company. */
interface RelationshipRepositoryInterface
{
    /** True when the relationship exists in the tenant and its person side is the given customer. */
    public function existsForPerson(string $hqId, string $personCustomerId, string $relationshipId): bool;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): RelationshipRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $relationshipId, array $attributes): void;

    public function findForTenant(string $hqId, string $relationshipId): ?RelationshipRecord;

    /** Reads the relationship for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $relationshipId): ?RelationshipRecord;

    /**
     * The relationships in which the customer is the person or the company, primary first, then newest
     * first. With `$today` (a Y-m-d date) only the relationships running on that day are returned: no
     * end date or one not before today, and no start date or one not after today.
     *
     * @return Collection<int, RelationshipRecord>
     */
    public function listForCustomer(string $hqId, string $customerId, ?string $today = null): Collection;

    /**
     * The relationship of one person with one company that has not ended by `$today` (no end date, or one
     * not before today), whether or not it has started yet. Read for update when `$lock` is set.
     */
    public function findOpenForPair(string $hqId, string $personCustomerId, string $companyCustomerId, string $today, bool $lock = false): ?RelationshipRecord;

    /**
     * The relationship that holds the primary-contact slot of the company: flagged primary and not ended
     * by `$today`. Read for update when `$lock` is set.
     */
    public function findOpenPrimaryForCompany(string $hqId, string $companyCustomerId, string $today, bool $lock = false): ?RelationshipRecord;
}
