<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class CatalogSelectionDto
{
    public function __construct(public ?string $offeringReference, public ?string $optionReference) {}
}
