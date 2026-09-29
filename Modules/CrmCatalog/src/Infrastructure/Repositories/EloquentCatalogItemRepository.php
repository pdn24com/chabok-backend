<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Application\Dto\CatalogItemFiltersDto;
use Modules\CrmCatalog\Application\Repositories\CatalogItemRepositoryInterface;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;

final class EloquentCatalogItemRepository implements CatalogItemRepositoryInterface
{
    public function create(array $attributes): CatalogItemRecord
    {
        return CatalogItemRecord::query()->forceCreate($attributes);
    }

    public function listForTenant(string $hqId, CatalogItemFiltersDto $filters): Collection
    {
        $query = $this->ofTenant($hqId)
            // One query each for the categories and for the industry links with their industries,
            // however many items the table shows.
            ->with([
                'category' => fn ($category) => $category->select(['id', 'title']),
                'industryLinks.industry' => fn ($industry) => $industry->select(['id', 'title']),
            ]);

        if ($filters->kind !== null) {
            $query->where('kind', $filters->kind->value);
        }
        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }
        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }
        if ($filters->search !== null && $filters->search !== '') {
            $term = '%'.addcslashes($filters->search, '%_\\').'%';
            $query->where(fn ($match) => $match->where('code', 'like', $term)->orWhere('title', 'like', $term));
        }

        return $query->orderBy('code')->orderBy('id')->get();
    }

    public function findForTenant(string $hqId, string $catalogItemId): ?CatalogItemRecord
    {
        return $this->ofTenant($hqId)
            ->with(['category', 'buyerPersona', 'salesModel', 'industryLinks.industry'])
            ->whereKey($catalogItemId)
            ->first();
    }

    public function lockForTenant(string $hqId, string $catalogItemId): ?CatalogItemRecord
    {
        return $this->ofTenant($hqId)->whereKey($catalogItemId)->lockForUpdate()->first();
    }

    public function codeTaken(string $hqId, string $code, ?string $exceptCatalogItemId = null): bool
    {
        $query = $this->ofTenant($hqId)->where('code', $code);
        if ($exceptCatalogItemId !== null) {
            $query->whereKeyNot($exceptCatalogItemId);
        }

        return $query->exists();
    }

    public function update(string $hqId, string $catalogItemId, array $attributes): void
    {
        CatalogItemRecord::query()->where(['hq_id' => $hqId, 'id' => $catalogItemId])->update($attributes);
    }

    /** @return Builder<CatalogItemRecord> */
    private function ofTenant(string $hqId): Builder
    {
        return CatalogItemRecord::query()->where('hq_id', $hqId);
    }
}
