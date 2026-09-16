<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

interface QuoteCatalogGuard
{
    public function assertQuoteCurrent(object $quote): void;
}
