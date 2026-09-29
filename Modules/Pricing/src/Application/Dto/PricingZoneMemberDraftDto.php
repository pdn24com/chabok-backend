<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Domain\Enums\ZoneMemberType;

/** Working draft: normalization updates postal, geographic and polygon values. */
final class PricingZoneMemberDraftDto
{
    /** @param array<string, mixed>|null $geometry GeoJSON at the geography boundary. */
    public function __construct(public ZoneMemberType $type, public string $reference = '', public ?string $cityId = null,
        public ?string $provinceId = null, public ?string $rangeEnd = null, public ?array $geometry = null) {}
}
