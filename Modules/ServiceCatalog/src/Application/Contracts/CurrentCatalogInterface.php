<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

interface CurrentCatalogInterface extends CatalogResolverInterface, QuoteCatalogGuardInterface
{
    public function currentVersion(CatalogResource $resource, string $reference, ?string $hqId = null): string;
}
