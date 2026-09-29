<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Modules\Pricing\Application\Dto\PricingZoneDraftDto;
use Modules\Pricing\Application\Dto\PricingZoneMemberDraftDto;
use Modules\Pricing\Domain\Enums\ZoneMemberType;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneRecord;

final class PricingZoneInput
{
    /** @return list<PricingZoneDraftDto> */
    public static function many(array $zones): array
    {
        $drafts = [];
        foreach ($zones as $zone) {
            $members = [];
            foreach ($zone['members'] ?? [] as $member) {
                $members[] = new PricingZoneMemberDraftDto(ZoneMemberType::from($member['member_type']), $member['reference_value'] ?? '',
                    $member['city_id'] ?? null, $member['province_id'] ?? null, $member['range_end'] ?? null, $member['geometry'] ?? null);
            }
            $drafts[] = new PricingZoneDraftDto($zone['pricing_zone_id'] ?? null, $zone['code'], $zone['title'], (bool) ($zone['remote_area'] ?? false), $zone['rank'] ?? null, $members);
        }

        return $drafts;
    }

    /** @param iterable<PricingZoneRecord> $zones @return list<PricingZoneDraftDto> */
    public static function fromRecords(iterable $zones): array
    {
        $drafts = [];
        foreach ($zones as $zone) {
            $members = [];
            foreach ($zone->members as $member) {
                $members[] = new PricingZoneMemberDraftDto(ZoneMemberType::from($member->member_type), $member->reference_value,
                    $member->city_id, $member->city?->province_id ?? $member->province_id, $member->range_end, $member->geometry);
            }
            $drafts[] = new PricingZoneDraftDto($zone->pricing_zone_id, $zone->code, $zone->title, (bool) $zone->remote_area, $zone->rank, $members);
        }

        return $drafts;
    }
}
