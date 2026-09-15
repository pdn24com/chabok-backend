<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Illuminate\Support\Facades\DB;

final class EditPricingImpact
{
    /** Fields which do not affect the accepted tariff or its geographic dependencies. */
    public function contactFields(array $row): array
    {
        $safe = ['contact_name', 'mobile', 'phone'];
        $quote = DB::table('pricing_snapshots as s')->join('pricing_quotes as q', 'q.quote_id', '=', 's.quote_id')
            ->where('s.hq_id', $row['hq_id'])->where('s.pricing_snapshot_id', $row['active_pricing_snapshot_id'] ?? null)
            ->first(['q.zone_set_version_id']);
        if (!$quote) return $safe;
        $safe[] = 'address_text';
        $group = DB::table('pricing_zone_set_versions')->where('zone_set_version_id', $quote->zone_set_version_id)->value('pricing_zone_set_id');
        if (!$group) return $safe;
        $groups = [$group];
        if (!empty($row['commitment_schedule_version_id'])) {
            $schedule = DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id', $row['commitment_schedule_version_id'])->value('commitment_schedule_id');
            $policy = DB::table('commitment_schedule_versions')->where('commitment_schedule_id', $schedule)->where('status', 'PUBLISHED')->orderByDesc('version_number')->value('commitment_policy');
            $policy = json_decode($policy ?? '{}', true);
            if (!empty($policy['zone_set_id'])) $groups[] = $policy['zone_set_id'];
        }
        $types = DB::table('pricing_zone_members as m')->join('pricing_zones as z', 'z.pricing_zone_id', '=', 'm.pricing_zone_id')
            ->join('pricing_zone_set_versions as v', 'v.zone_set_version_id', '=', 'z.zone_set_version_id')
            ->whereIn('v.pricing_zone_set_id', $groups)->where(fn ($query) => $query->where('v.hq_id', $row['hq_id'])->orWhereNull('v.hq_id'))
            ->whereIn('v.status', ['PUBLISHED', 'SUPERSEDED'])->pluck('m.member_type')->all();
        if (!in_array('POLYGON', $types, true)) $safe = [...$safe, 'latitude', 'longitude'];
        if (!in_array('POSTAL_RANGE', $types, true)) $safe[] = 'postal_code';
        return $safe;
    }

    public function changed(array $before, array $after, array $safeContactFields): bool
    {
        foreach (['sender', 'receiver'] as $party) {
            // Geography canonicalization enriches contacts with derived metadata.
            foreach (['city_reference', 'province_id', 'legacy_city_code'] as $field) {
                unset($before[$party][$field], $after[$party][$field]);
            }
            foreach ($safeContactFields as $field) { unset($before[$party][$field], $after[$party][$field]); }
        }
        foreach ($before['parcels'] as &$parcel) unset($parcel['content_description']);
        unset($parcel);
        foreach ($after['parcels'] as &$parcel) unset($parcel['content_description']);
        unset($parcel);
        return $before != $after;
    }
}
