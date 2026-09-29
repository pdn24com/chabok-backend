<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use Modules\Authorization\Application\Repositories\TenantEntitlementRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\TenantEntitlementRecord;

final class EloquentTenantEntitlementRepository implements TenantEntitlementRepositoryInterface
{
    /** Columns an entitlement listing returns. */
    private const LISTING_COLUMNS = ['module_code', 'status'];

    public function statusFor(?string $hqId, string $moduleCode): ?string
    {
        $status = TenantEntitlementRecord::query()->where(['hq_id' => $hqId, 'module_code' => $moduleCode])->value('status');

        return $status === null ? null : (string) $status;
    }

    public function forTenant(string $hqId): array
    {
        return TenantEntitlementRecord::query()
            ->where('hq_id', $hqId)
            ->orderBy('module_code')
            ->get(self::LISTING_COLUMNS)
            ->all();
    }

    public function acrossTenants(): array
    {
        return TenantEntitlementRecord::query()
            ->orderBy('hq_id')
            ->orderBy('module_code')
            ->get(self::LISTING_COLUMNS)
            ->all();
    }
}
