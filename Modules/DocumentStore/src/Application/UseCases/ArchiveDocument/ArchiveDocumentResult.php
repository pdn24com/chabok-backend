<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ArchiveDocument;

use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentRecord;

/** The document once it has been retired from the archive. */
final readonly class ArchiveDocumentResult
{
    public function __construct(
        public DocumentRecord $document,
    ) {}
}
