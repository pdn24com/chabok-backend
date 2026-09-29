<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class MatrixDefinitionDto
{
    /** @param list<string> $zoneIds */
    public function __construct(
        public string $id,
        public ?string $serviceOfferingVersionId,
        public ?string $serviceOptionVersionId,
        public ?string $originZoneId,
        public array $zoneIds,
    ) {}
}
