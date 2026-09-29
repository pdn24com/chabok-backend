<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\LinkDocumentToResource;

use Modules\DocumentStore\Application\Dto\DocumentLinkDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class LinkDocumentToResourceCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $documentId,
        public DocumentLinkDto $input,
    ) {}
}
