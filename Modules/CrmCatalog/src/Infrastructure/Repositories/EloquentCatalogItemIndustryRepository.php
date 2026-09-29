<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Repositories;

use DateTimeImmutable;
use Modules\CrmCatalog\Application\Repositories\CatalogItemIndustryRepositoryInterface;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemIndustryRecord;

final class EloquentCatalogItemIndustryRepository implements CatalogItemIndustryRepositoryInterface
{
    public function industryIdsForItem(string $hqId, string $catalogItemId): array
    {
        return CatalogItemIndustryRecord::query()
            ->where(['hq_id' => $hqId, 'catalog_item_id' => $catalogItemId])
            ->orderBy('id')
            ->pluck('industry_id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    public function attach(string $hqId, string $catalogItemId, array $industryIds, string $createdBy, DateTimeImmutable $at): void
    {
        foreach ($industryIds as $industryId) {
            CatalogItemIndustryRecord::query()->forceCreate([
                'hq_id' => $hqId,
                'catalog_item_id' => $catalogItemId,
                'industry_id' => $industryId,
                'created_by' => $createdBy,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    public function detach(string $hqId, string $catalogItemId, array $industryIds): void
    {
        if ($industryIds === []) {
            return;
        }
        CatalogItemIndustryRecord::query()
            ->where(['hq_id' => $hqId, 'catalog_item_id' => $catalogItemId])
            ->whereIn('industry_id', $industryIds)
            ->delete();
    }
}
