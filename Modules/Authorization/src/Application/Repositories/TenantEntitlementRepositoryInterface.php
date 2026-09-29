<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

use Modules\Authorization\Infrastructure\Persistence\Models\TenantEntitlementRecord;

interface TenantEntitlementRepositoryInterface
{
    public function statusFor(?string $hqId, string $moduleCode): ?string;

    /** @return list<TenantEntitlementRecord> */
    public function forTenant(string $hqId): array;

    /** @return list<TenantEntitlementRecord> */
    public function acrossTenants(): array;
}
