<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Application\Dto\TariffHistoryEntryDto;
use Modules\Pricing\Application\Serialization\PricingVersionSnapshot;

final class PricingHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entry = $this->resource;
        if (! $entry instanceof TariffHistoryEntryDto) {
            return PricingVersionSnapshot::serialize($entry);
        }
        $data = PricingVersionSnapshot::serialize($entry->version);
        $data['configured_zone_set'] = $entry->configuredZoneSet === null ? null : PricingVersionSnapshot::serialize($entry->configuredZoneSet);
        if ($entry->resolutionAt !== null) {
            $data['zone_resolution_at'] = CarbonImmutable::instance($entry->resolutionAt)->toISOString();
            $data['effective_zone_set'] = $entry->effectiveZoneSet === null ? null : PricingVersionSnapshot::serialize($entry->effectiveZoneSet);
            $data['zone_resolution_error'] = $entry->effectiveZoneSet === null ? 'PRICING_ZONE_VERSION_UNAVAILABLE_OR_AMBIGUOUS' : null;
        }

        return $data;
    }
}
