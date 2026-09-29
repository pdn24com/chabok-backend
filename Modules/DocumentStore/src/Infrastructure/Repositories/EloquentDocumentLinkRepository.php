<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Repositories;

use Modules\DocumentStore\Application\Repositories\DocumentLinkRepositoryInterface;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentLinkRecord;

final class EloquentDocumentLinkRepository implements DocumentLinkRepositoryInterface
{
    public function create(array $attributes): DocumentLinkRecord
    {
        return DocumentLinkRecord::query()->forceCreate($attributes);
    }

    public function exists(string $hqId, string $documentId, DocumentResourceType $resourceType, string $resourceId): bool
    {
        return DocumentLinkRecord::query()
            ->where([
                'hq_id' => $hqId,
                'document_id' => $documentId,
                'resource_type' => $resourceType->value,
                'resource_id' => $resourceId,
            ])
            ->exists();
    }

    public function documentsForResources(string $hqId, DocumentResourceType $resourceType, array $resourceIds): array
    {
        if ($resourceIds === []) {
            return [];
        }

        $grouped = [];
        $links = DocumentLinkRecord::query()
            ->where(['hq_id' => $hqId, 'resource_type' => $resourceType->value])
            ->whereIn('resource_id', $resourceIds)
            ->with(['document' => fn ($document) => $document->select(['id', 'title'])])
            ->orderBy('id')
            ->get();
        foreach ($links as $link) {
            if ($link->document === null) {
                continue;
            }
            $grouped[(string) $link->resource_id][] = ['document_id' => $link->document->document_id, 'title' => $link->document->title];
        }

        return $grouped;
    }
}
