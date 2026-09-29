<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneMemberRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

/** The published content digest and public version document share this stable schema. */
final class PricingVersionSnapshot
{
    public static function serialize(TariffVersionRecord|PricingZoneSetVersionRecord $version): array
    {
        return $version instanceof TariffVersionRecord ? self::tariff($version) : self::zoneSet($version);
    }

    private static function tariff(TariffVersionRecord $version): array
    {
        $attributes = $version->attributesToArray();
        // Preserve the established version document's database timestamp and flag format.
        $attributes['valid_from'] = $version->getRawOriginal('valid_from');
        $attributes['valid_to'] = $version->getRawOriginal('valid_to');
        $attributes['is_default'] = (int) $version->is_default;
        $family = $version->family;
        $rules = [];
        foreach ($version->rules as $rule) {
            $rules[] = $rule->attributesToArray();
        }

        return [...$attributes, 'code' => $family->code, 'title' => $family->title, 'purpose' => $family->purpose,
            'currency' => $family->currency, 'scope_type' => $family->scope_type, 'scope_value' => $family->scope_value,
            'priority' => $family->priority, 'tariff_kind' => $family->tariff_kind, 'service_charge_type_id' => $family->service_charge_type_id,
            'service_tariff_family_ids' => $version->serviceAttachments->pluck('service_tariff_family_id')->all(), 'rules' => $rules];
    }

    private static function zoneSet(PricingZoneSetVersionRecord $version): array
    {
        $zones = [];
        foreach ($version->zones as $zone) {
            $members = [];
            foreach ($zone->members as $member) {
                $members[] = self::member($member);
            }
            $zones[] = [...$zone->attributesToArray(), 'members' => $members];
        }

        return [...$version->attributesToArray(), 'code' => $version->zoneSet->code, 'title' => $version->zoneSet->title,
            'purpose' => $version->zoneSet->purpose, 'zones' => $zones];
    }

    private static function member(PricingZoneMemberRecord $member): array
    {
        $city = $member->city;
        $province = $city?->province ?? $member->province;

        return ['zone_member_id' => $member->zone_member_id, 'pricing_zone_id' => $member->pricing_zone_id,
            'member_type' => $member->member_type, 'reference_value' => $member->reference_value, 'city_id' => $member->city_id,
            'province_id' => $city?->province_id ?? $member->province_id, 'geometry' => $member->geometry,
            'range_end' => $member->range_end, 'precedence' => $member->precedence,
            'city_name_fa' => $city?->name_fa, 'legacy_city_code' => $city?->legacy_city_code,
            'province_name_fa' => $province?->name_fa, 'legacy_province_code' => $province?->legacy_province_code];
    }
}
