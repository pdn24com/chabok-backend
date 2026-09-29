<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogItems;

use Modules\CrmCatalog\Application\Dto\CatalogItemFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCatalogItemsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public CatalogItemFiltersDto $filters = new CatalogItemFiltersDto,
    ) {}
}
