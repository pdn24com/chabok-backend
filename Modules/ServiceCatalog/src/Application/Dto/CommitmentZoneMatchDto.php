<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class CommitmentZoneMatchDto
{
    public function __construct(
        public string $zoneId,
        public string $code,
        public string $title,
        public ?int $rank,
        public bool $remoteArea,
        public string $memberId,
        public string $memberType,
        public int $precedence,
    ) {}
}
