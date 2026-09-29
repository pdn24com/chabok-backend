<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final class OfferingAvailabilityDto
{
    public function __construct(
        public string $scopeType = '',
        public ?string $scopeValue = null,
        public bool $enabled = true,
        /** @var list<string> */ public array $presentFields = [],
    ) {}
}
