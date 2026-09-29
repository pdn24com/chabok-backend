<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class PricingZoneColumnDto
{
    public function __construct(public string $id, public int|float|string|null $rank) {}
}
