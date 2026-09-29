<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\RegisterDocument;

use Modules\DocumentStore\Application\Dto\DocumentDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class RegisterDocumentCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public DocumentDraftDto $input) {}
}
