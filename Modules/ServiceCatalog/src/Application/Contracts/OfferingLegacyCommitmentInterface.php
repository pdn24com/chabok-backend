<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

interface OfferingLegacyCommitmentInterface
{
    public function commitment(ServiceOfferingVersionRecord $row, OfferingSelectionContext $context): OfferingCommitmentDto;
}
