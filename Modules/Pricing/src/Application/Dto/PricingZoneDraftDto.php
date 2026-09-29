<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final class PricingZoneDraftDto
{
    /** @param list<PricingZoneMemberDraftDto> $members */
    public function __construct(public ?string $id, public string $code, public string $title, public bool $remoteArea,
        public int|float|string|null $rank, public array $members) {}
}
