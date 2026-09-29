<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\LinkDocumentToResource;

use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentLinkRecord;

/** The attachment just made between a document and a record. */
final readonly class LinkDocumentToResourceResult
{
    public function __construct(
        public DocumentLinkRecord $link,
    ) {}
}
