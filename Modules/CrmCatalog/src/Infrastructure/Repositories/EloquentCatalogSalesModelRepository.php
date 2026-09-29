<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Application\Repositories\CatalogSalesModelRepositoryInterface;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogSalesModelRecord;

final class EloquentCatalogSalesModelRepository implements CatalogSalesModelRepositoryInterface
{
    public function listForTenant(string $hqId, bool $activeOnly = false): Collection
    {
        $query = CatalogSalesModelRecord::query()->where('hq_id', $hqId);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function activeExistsInTenant(string $hqId, string $id): bool
    {
        return CatalogSalesModelRecord::query()->where(['hq_id' => $hqId, 'id' => $id, 'is_active' => true])->exists();
    }
}
