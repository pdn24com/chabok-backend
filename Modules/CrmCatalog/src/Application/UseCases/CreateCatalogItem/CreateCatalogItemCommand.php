<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\CreateCatalogItem;

use Modules\CrmCatalog\Application\Dto\CatalogItemDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCatalogItemCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public CatalogItemDraftDto $input,
    ) {}
}
