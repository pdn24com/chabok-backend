<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class ServiceTariffEvidenceDto
{
    public function __construct(
        public string $familyId,
        public string $versionId,
        public int $versionNumber,
        public string $chargeCode,
        public bool $applied,
        public ?string $zoneSetVersionId,
    ) {}
}
