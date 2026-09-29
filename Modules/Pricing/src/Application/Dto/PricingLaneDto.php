<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class PricingLaneDto
{
    public function __construct(public ZoneResolutionDto $origin, public ZoneResolutionDto $destination) {}
}
