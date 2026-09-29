<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationsDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentResolutionDto;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

interface OfferingCommitmentResolverInterface
{
    public function resolve(ServiceOfferingVersionRecord $offering, OfferingSelectionContext $context, bool $requireSelection = true, ?CommitmentDestinationsDto $destinations = null): ?OfferingCommitmentDto;

    public function inspect(ServiceOfferingVersionRecord $offering, OfferingSelectionContext $context, bool $requireSelection = true, ?CommitmentDestinationsDto $destinations = null): OfferingCommitmentResolutionDto;
}
