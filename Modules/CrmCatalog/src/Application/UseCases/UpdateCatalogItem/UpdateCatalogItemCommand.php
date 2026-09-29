<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\UpdateCatalogItem;

use Modules\CrmCatalog\Application\Dto\CatalogItemChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateCatalogItemCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $catalogItemId,
        public CatalogItemChangesDto $changes,
    ) {}
}
