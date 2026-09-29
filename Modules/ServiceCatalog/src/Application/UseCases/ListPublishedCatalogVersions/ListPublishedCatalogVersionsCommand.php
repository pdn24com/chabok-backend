<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListPublishedCatalogVersionsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public array $filters,
    ) {}
}
