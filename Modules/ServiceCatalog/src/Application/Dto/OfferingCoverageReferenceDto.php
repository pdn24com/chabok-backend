<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final class OfferingCoverageReferenceDto
{
    public function __construct(
        public string $direction = 'BOTH',
        public string $referenceType = '',
        public string $referenceValue = '',
        public ?string $secondaryReferenceValue = null,
        public int $priority = 100,
        /** @var list<string> */ public array $presentFields = [],
    ) {}
}
