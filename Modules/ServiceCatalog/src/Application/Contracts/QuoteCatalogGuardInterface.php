<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\QuoteCatalogReferencesDto;

interface QuoteCatalogGuardInterface
{
    public function assertQuoteCurrent(QuoteCatalogReferencesDto $quote): void;
}
