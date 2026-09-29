<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CatalogSelectionAvailabilityDto;
use Modules\ServiceCatalog\Application\Dto\CatalogSelectionDto;

interface CatalogSelectionInspectorInterface
{
    /** @param list<CatalogSelectionDto> $selections @return list<CatalogSelectionAvailabilityDto> Results retain selection order. */
    public function inspect(string $hqId, array $selections): array;
}
