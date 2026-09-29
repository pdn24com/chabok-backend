<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Application\Repositories\CatalogCategoryRepositoryInterface;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogCategoryRecord;

final class EloquentCatalogCategoryRepository implements CatalogCategoryRepositoryInterface
{
    public function listForTenant(string $hqId, bool $activeOnly = false): Collection
    {
        $query = CatalogCategoryRecord::query()->where('hq_id', $hqId);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')->orderBy('id')->get();
    }

    public function activeExistsInTenant(string $hqId, string $id): bool
    {
        return CatalogCategoryRecord::query()->where(['hq_id' => $hqId, 'id' => $id, 'is_active' => true])->exists();
    }
}
