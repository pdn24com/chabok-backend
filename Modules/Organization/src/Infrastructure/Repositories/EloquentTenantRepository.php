<?php

declare(strict_types=1);

namespace Modules\Organization\Infrastructure\Repositories;

use Modules\Organization\Application\Repositories\TenantRepositoryInterface;
use Modules\Organization\Infrastructure\Persistence\Models\HqTenantRecord;

final class EloquentTenantRepository implements TenantRepositoryInterface
{
    /** Columns an authorization context exposes about the acting tenant. */
    private const IDENTITY_COLUMNS = ['hq_id', 'hq_code', 'hq_title'];

    public function findIdentity(string $hqId): ?HqTenantRecord
    {
        return HqTenantRecord::query()->where('hq_id', $hqId)->first(self::IDENTITY_COLUMNS);
    }

    public function findByCode(string $hqCode): ?HqTenantRecord
    {
        return HqTenantRecord::query()->where('hq_code', $hqCode)->first();
    }

    public function lockIdentity(?string $hqId): void
    {
        HqTenantRecord::query()->where('hq_id', $hqId)->lockForUpdate()->first();
    }
}
