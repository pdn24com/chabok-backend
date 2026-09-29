<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\UpdateDocument;

use Modules\DocumentStore\Application\Dto\DocumentChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateDocumentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $documentId,
        public DocumentChangesDto $changes,
    ) {}
}
