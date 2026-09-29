<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ArchiveDocument;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ArchiveDocumentCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $documentId) {}
}
