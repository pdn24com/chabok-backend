<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class CommitmentDestinationDto
{
    public function __construct(
        public string $zoneSetId,
        public string $zoneSetVersionId,
        public ?CommitmentZoneMatchDto $match,
    ) {}
}
