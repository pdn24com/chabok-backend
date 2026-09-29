<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use DateTimeImmutable;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class TariffHistoryEntryDto
{
    public function __construct(public TariffVersionRecord $version, public ?PricingZoneSetVersionRecord $configuredZoneSet,
        public ?PricingZoneSetVersionRecord $effectiveZoneSet, public ?DateTimeImmutable $resolutionAt) {}
}
