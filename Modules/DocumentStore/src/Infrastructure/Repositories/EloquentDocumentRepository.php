<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\DocumentStore\Application\Dto\DocumentListFiltersDto;
use Modules\DocumentStore\Application\Repositories\DocumentRepositoryInterface;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentRecord;

final class EloquentDocumentRepository implements DocumentRepositoryInterface
{
    public function paginateForTenant(string $hqId, DocumentListFiltersDto $filters): LengthAwarePaginator
    {
        $query = DocumentRecord::query()
            ->where('hq_id', $hqId)
            ->with([
                'category' => fn ($category) => $category->select(['id', 'title', 'parent_id', 'active']),
                'links',
            ]);

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }
        if ($filters->categoryId !== null) {
            $query->where('category_id', $filters->categoryId);
        }
        // One term against both the title and the reference number, which is how an operator searches.
        if ($filters->search !== null) {
            $query->where(fn ($match) => $match
                ->whereLike('title', '%'.$filters->search.'%')
                ->orWhereLike('reference_no', '%'.$filters->search.'%'));
        }
        // Narrowing to one record is what turns the archive into the documents tab of that record.
        if ($filters->resourceType !== null && $filters->resourceId !== null) {
            $query->whereHas('links', fn ($link) => $link->where([
                'resource_type' => $filters->resourceType->value,
                'resource_id' => $filters->resourceId,
            ]));
        }

        return $query->orderByDesc('id')->paginate($filters->perPage, page: $filters->page);
    }

    public function findForTenant(string $hqId, string $documentId): ?DocumentRecord
    {
        return DocumentRecord::query()
            ->where(['hq_id' => $hqId, 'document_id' => $documentId])
            ->with([
                'category' => fn ($category) => $category->select(['id', 'title', 'parent_id', 'active']),
                'links',
            ])
            ->first();
    }

    public function lockForTenant(string $hqId, string $documentId): ?DocumentRecord
    {
        return DocumentRecord::query()->where(['hq_id' => $hqId, 'document_id' => $documentId])->lockForUpdate()->first();
    }

    public function create(array $attributes): DocumentRecord
    {
        return DocumentRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $documentId, array $attributes): void
    {
        DocumentRecord::query()->where(['hq_id' => $hqId, 'document_id' => $documentId])->update($attributes);
    }
}
