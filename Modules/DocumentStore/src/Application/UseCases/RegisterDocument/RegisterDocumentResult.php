<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\RegisterDocument;

use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentRecord;

/** The registered document, with its category and everything it was attached to read back. */
final readonly class RegisterDocumentResult
{
    public function __construct(
        public DocumentRecord $document,
    ) {}
}
