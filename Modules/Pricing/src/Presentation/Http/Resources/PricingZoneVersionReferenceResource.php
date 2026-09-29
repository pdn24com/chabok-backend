<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

/** @mixin PricingZoneSetVersionRecord */
final class PricingZoneVersionReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $zones = [];
        foreach ($this->zones as $zone) {
            $zones[] = ['pricing_zone_id' => $zone->pricing_zone_id, 'code' => $zone->code, 'title' => $zone->title,
                'remote_area' => (bool) $zone->remote_area, 'rank' => $zone->rank];
        }

        return ['zone_set_version_id' => $this->zone_set_version_id, 'pricing_zone_set_id' => $this->pricing_zone_set_id,
            'version_number' => $this->version_number, 'status' => $this->status, 'valid_from' => $this->valid_from, 'valid_to' => $this->valid_to,
            'code' => $this->zoneSet->code, 'title' => $this->zoneSet->title, 'purpose' => $this->zoneSet->purpose, 'zones' => $zones];
    }
}
