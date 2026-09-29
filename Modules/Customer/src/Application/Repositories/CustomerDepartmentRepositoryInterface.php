<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;

interface CustomerDepartmentRepositoryInterface
{
    /**
     * The whole department chart of one company, flat and with the posts of each node loaded.
     *
     * @return Collection<int, CustomerDepartmentRecord>
     */
    public function listForCompany(string $hqId, string $customerId): Collection;

    public function findForCompany(string $hqId, string $customerId, string $departmentId): ?CustomerDepartmentRecord;

    /** Reads the node for update; the caller must already be inside a transaction. */
    public function lockForCompany(string $hqId, string $customerId, string $departmentId): ?CustomerDepartmentRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): CustomerDepartmentRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $departmentId, array $attributes): void;

    /**
     * The node IDs on the path from one node up to the root of its chart, nearest first.
     *
     * @return list<string>
     */
    public function ancestorIds(string $hqId, string $departmentId): array;
}
