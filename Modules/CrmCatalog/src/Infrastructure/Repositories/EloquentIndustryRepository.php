<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CrmCatalog\Application\Dto\IndustryListFiltersDto;
use Modules\CrmCatalog\Application\Repositories\IndustryRepositoryInterface;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\IndustryRecord;

final class EloquentIndustryRepository implements IndustryRepositoryInterface
{
    public function paginateForTenant(string $hqId, IndustryListFiltersDto $filters): LengthAwarePaginator
    {
        $query = IndustryRecord::query()->where('hq_id', $hqId);

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }
        if ($filters->search !== null) {
            $query->where(fn ($match) => $match->whereLike('title', '%'.$filters->search.'%')
                ->orWhereLike('code', '%'.$filters->search.'%'));
        }

        // sort_order drives the display order the knowledge base was curated in; title breaks its ties.
        return $query
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate($filters->perPage, ['id', 'code', 'title', 'description', 'is_active', 'sort_order'], page: $filters->page);
    }

    public function activeExistsInTenant(string $hqId, string $industryId): bool
    {
        return IndustryRecord::query()->where(['hq_id' => $hqId, 'id' => $industryId, 'is_active' => true])->exists();
    }

    public function activeIdsInTenant(string $hqId, array $industryIds): array
    {
        return IndustryRecord::query()
            ->where(['hq_id' => $hqId, 'is_active' => true])
            ->whereIn('id', $industryIds)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }
}
