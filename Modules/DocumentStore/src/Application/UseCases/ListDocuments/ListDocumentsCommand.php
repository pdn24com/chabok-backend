<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ListDocuments;

use Modules\DocumentStore\Application\Dto\DocumentListFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListDocumentsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public DocumentListFiltersDto $filters) {}
}
