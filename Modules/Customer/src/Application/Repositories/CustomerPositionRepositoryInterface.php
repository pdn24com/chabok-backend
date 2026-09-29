<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerPositionRecord;

interface CustomerPositionRepositoryInterface
{
    public function findForCompany(string $hqId, string $customerId, string $positionId): ?CustomerPositionRecord;

    /** Reads the post for update; the caller must already be inside a transaction. */
    public function lockForCompany(string $hqId, string $customerId, string $positionId): ?CustomerPositionRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerPositionRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $positionId, array $attributes): void;

    /**
     * The titles of the given posts of the tenant, keyed by ID, so a caller naming a list of rows reads
     * them in one query instead of one per row.
     *
     * @param  list<string>  $positionIds
     * @return array<string, string>
     */
    public function titlesFor(string $hqId, array $positionIds): array;
}
