<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ListDocumentCategories;

use Modules\DocumentStore\Application\Dto\DocumentCategoryFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListDocumentCategoriesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public DocumentCategoryFiltersDto $filters) {}
}
