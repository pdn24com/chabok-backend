<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneMemberRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneRecord;

final readonly class ZoneResolutionDto
{
    public function __construct(public PricingZoneRecord $zone, public PricingZoneMemberRecord $member, public int $precedence) {}
}
