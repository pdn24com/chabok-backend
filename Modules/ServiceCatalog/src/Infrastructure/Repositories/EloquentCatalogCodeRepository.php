<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\ServiceCatalog\Application\Repositories\CatalogCodeRepository;
use Modules\ServiceCatalog\Infrastructure\Persistence\CatalogStorageMap;

final class EloquentCatalogCodeRepository implements CatalogCodeRepository
{
    public function lockOwner(string $owner): void
    {
        DB::table('hq_tenants')->where('hq_id', $owner)->lockForUpdate()->first();
    }

    public function exists(string $resource, string $owner, string $code): bool
    {
        return CatalogStorageMap::query(CatalogStorageMap::MAP[$resource][0])->where('owner_key', $owner)->where('code', $code)->exists();
    }
}
