<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Modules\ServiceCatalog\Domain\ValueObjects\OfferingEligibilityDecision;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final readonly class ResolvedServiceOfferingDto
{
    /** @param list<SelectedServiceOptionDto> $options */
    public function __construct(public ServiceOfferingVersionRecord $version, public OfferingDependencyVersionsDto $dependencies,
        public OfferingEligibilityDecision $decision, public array $options, public ?OfferingCommitmentDto $commitment) {}
}
