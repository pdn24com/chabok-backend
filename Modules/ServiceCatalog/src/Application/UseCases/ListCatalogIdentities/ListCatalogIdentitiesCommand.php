<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCatalogIdentitiesCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public array $filters,
    ) {}
}
