<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\ServiceCatalog\Application\Contracts\QuoteCatalogGuard;

final class CurrentQuoteCatalogGuard implements QuoteCatalogGuard
{
    public function __construct(private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog)
    {
    }

    public function assertQuoteCurrent(object $quote): void
    {
        $this->currentCatalog->assertQuoteCurrent($quote);
    }
}
