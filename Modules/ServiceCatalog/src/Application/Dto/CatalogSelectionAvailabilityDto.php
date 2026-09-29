<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class CatalogSelectionAvailabilityDto
{
    public function __construct(public bool $offeringPublished, public bool $optionBound, public bool $offeringAvailable) {}
}
