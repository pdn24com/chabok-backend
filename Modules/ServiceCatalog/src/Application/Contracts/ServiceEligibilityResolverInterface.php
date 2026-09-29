<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\ServiceEligibilitySelectionDto;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

interface ServiceEligibilityResolverInterface
{
    public function validateSelection(
        AuthenticatedPrincipal $actor,
        string $offeringId,
        ?string $versionId,
        OfferingSelectionContext $context,
    ): ServiceEligibilitySelectionDto;
}
