<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;

interface OfferingReferenceGuardInterface
{
    public function assertOfferingReferences(CatalogDraftDto $input, ?string $hqId): void;
}
