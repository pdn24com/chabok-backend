<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\OptionRevisionSetDto;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingEligibilityDecision;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

interface OfferingEligibilityInterface
{
    /** @param list<OptionRevisionSetDto>|null $selectedOptions */
    public function evaluate(ServiceOfferingVersionRecord $row, OfferingSelectionContext $context, ?array $selectedOptions = null): OfferingEligibilityDecision;

    /** @return list<OptionRevisionSetDto> */
    public function selectedOptions(OfferingSelectionContext $context, ?string $owner): array;
}
