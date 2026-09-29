<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\DocumentStore\Application\Dto\DocumentListFiltersDto;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentRecord;

interface DocumentRepositoryInterface
{
    /**
     * The archive a page at a time, newest first, each document with its category and everything it is
     * attached to.
     *
     * @return LengthAwarePaginator<DocumentRecord>
     */
    public function paginateForTenant(string $hqId, DocumentListFiltersDto $filters): LengthAwarePaginator;

    public function findForTenant(string $hqId, string $documentId): ?DocumentRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $documentId): ?DocumentRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DocumentRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $documentId, array $attributes): void;
}
