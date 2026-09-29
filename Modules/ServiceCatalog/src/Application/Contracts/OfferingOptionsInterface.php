<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\SelectedServiceOptionDto;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

interface OfferingOptionsInterface
{
    /** @return list<SelectedServiceOptionDto> */
    public function resolvedOptions(ServiceOfferingVersionRecord $offering, OfferingSelectionContext $context): array;
}
