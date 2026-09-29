<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\GetCatalogItem;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCatalogItemCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $catalogItemId,
    ) {}
}
