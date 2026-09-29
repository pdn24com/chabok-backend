<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Repositories;

use Modules\Organization\Infrastructure\Persistence\Models\HqTenantRecord;

interface TenantRepositoryInterface
{
    /** Identity columns only; a context response never exposes the rest of the tenant row. */
    public function findIdentity(string $hqId): ?HqTenantRecord;

    public function findByCode(string $hqCode): ?HqTenantRecord;

    /** Serializes tenant-wide writes that would otherwise race, such as code allocation. */
    public function lockIdentity(?string $hqId): void;
}
