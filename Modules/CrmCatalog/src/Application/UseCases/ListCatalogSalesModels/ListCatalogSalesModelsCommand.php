<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogSalesModels;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCatalogSalesModelsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        /** True keeps only the entries the item form may still pick. */
        public bool $activeOnly = false,
    ) {}
}
