<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Consignment\Application\Repositories\PricingImpactReader;

final class SqlPricingImpactReader implements PricingImpactReader
{
    public function acceptedZoneVersion(string $hqId, ?string $snapshotId): ?object
    {
        return DB::table('pricing_snapshots as s')->join('pricing_quotes as q', 'q.quote_id', '=', 's.quote_id')->where('s.hq_id', $hqId)->where('s.pricing_snapshot_id', $snapshotId)->first(['q.zone_set_version_id']);
    }

    public function zoneSetId(string $versionId): ?string
    {
        return DB::table('pricing_zone_set_versions')->where('zone_set_version_id', $versionId)->value('pricing_zone_set_id');
    }

    public function commitmentScheduleId(string $versionId): ?string
    {
        return DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id', $versionId)->value('commitment_schedule_id');
    }

    public function publishedCommitmentPolicy(?string $schedule): ?string
    {
        return DB::table('commitment_schedule_versions')->where('commitment_schedule_id', $schedule)->where('status', 'PUBLISHED')->orderByDesc('version_number')->value('commitment_policy');
    }

    public function geographicMemberTypes(string $hqId, array $groups): array
    {
        return DB::table('pricing_zone_members as m')->join('pricing_zones as z', 'z.pricing_zone_id', '=', 'm.pricing_zone_id')->join('pricing_zone_set_versions as v', 'v.zone_set_version_id', '=', 'z.zone_set_version_id')->whereIn('v.pricing_zone_set_id', $groups)->where(fn($query) => $query->where('v.hq_id', $hqId)->orWhereNull('v.hq_id'))->whereIn('v.status', ['PUBLISHED', 'SUPERSEDED'])->pluck('m.member_type')->all();
    }
}
