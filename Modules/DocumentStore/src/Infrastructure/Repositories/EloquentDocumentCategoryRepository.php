<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\DocumentStore\Application\Repositories\DocumentCategoryRepositoryInterface;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentCategoryRecord;

final class EloquentDocumentCategoryRepository implements DocumentCategoryRepositoryInterface
{
    public function listForTenant(string $hqId, ?bool $active = null): Collection
    {
        return DocumentCategoryRecord::query()
            ->where('hq_id', $hqId)
            ->when($active !== null, fn ($query) => $query->where('active', $active))
            // Parents before their children, so a client can build the tree in one pass.
            ->orderByRaw('parent_id is not null')
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get();
    }

    public function activeExistsForTenant(string $hqId, string $categoryId): bool
    {
        return DocumentCategoryRecord::query()
            ->where(['hq_id' => $hqId, 'document_category_id' => $categoryId, 'active' => true])
            ->exists();
    }
}
