<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\UpdateDocument;

use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentRecord;

/** The document as it stands once the change has landed. */
final readonly class UpdateDocumentResult
{
    public function __construct(
        public DocumentRecord $document,
    ) {}
}
