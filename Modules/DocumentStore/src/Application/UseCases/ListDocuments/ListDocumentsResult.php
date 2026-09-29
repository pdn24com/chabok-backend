<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ListDocuments;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentRecord;

/** One page of the archive, newest first. */
final readonly class ListDocumentsResult
{
    /**
     * @param  LengthAwarePaginator<DocumentRecord>  $documents
     */
    public function __construct(
        public LengthAwarePaginator $documents,
    ) {}
}
