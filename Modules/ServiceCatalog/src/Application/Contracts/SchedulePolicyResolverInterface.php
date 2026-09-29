<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationsDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

interface SchedulePolicyResolverInterface
{
    public function resolvePolicy(CommitmentScheduleVersionRecord $version, OfferingSelectionContext $context, bool $requireSelection, string $hqId, ?CommitmentDestinationsDto $destinations = null): OfferingCommitmentDto;
}
