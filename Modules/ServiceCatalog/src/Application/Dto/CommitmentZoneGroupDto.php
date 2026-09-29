<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class CommitmentZoneGroupDto
{
    /** @param list<CommitmentZoneSummaryDto> $zones */
    public function __construct(public string $id, public string $versionId, public string $code, public string $title, public array $zones) {}

    /** @return list<string> */
    public function zoneCodes(): array
    {
        return array_map(static fn (CommitmentZoneSummaryDto $zone): string => $zone->code, $this->zones);
    }
}
