<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Dto;

use Modules\DocumentStore\Domain\Enums\DocumentResourceType;

/** One attachment of a document to a record, as the form sends it. */
final readonly class DocumentLinkDto
{
    public function __construct(
        public DocumentResourceType $resourceType,
        public string $resourceId,
        public ?string $purpose = null,
    ) {}
}
