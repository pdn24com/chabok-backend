<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

interface OfferingContextInterface
{
    public function canonicalizeCoverageContext(OfferingSelectionContext $context): OfferingSelectionContext;
}
