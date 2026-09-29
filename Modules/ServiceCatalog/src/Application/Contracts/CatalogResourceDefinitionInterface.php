<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

interface CatalogResourceDefinitionInterface
{
    /** @return array{string, string} The identity and version key names of a resource addressable by the draft endpoints. */
    public function map(string $resource): array;

    /** Resolves a path segment to its catalogue resource, or reports the route as unknown. */
    public function resource(string $resource): CatalogResource;
}
