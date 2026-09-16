<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Pricing\Application\Repositories\CommitmentZoneRepository;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneMemberRecord;

final class EloquentCommitmentZoneRepository implements CommitmentZoneRepository
{
    public function availableGroups(string $hqId, \DateTimeInterface $at): array
    {
        return PricingZoneSetRecord::query()->toBase()->from('pricing_zone_sets as s')->where(fn($q) => $q->where('s.hq_id', $hqId)->orWhereNull('s.hq_id'))->whereExists(fn($q) => $q->selectRaw('1')->from('pricing_zone_set_versions as v')->whereColumn('v.pricing_zone_set_id', 's.pricing_zone_set_id')->where('v.status', 'PUBLISHED')->where('valid_from', '<=', $at)->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $at)))->orderBy('s.title')->get()->all();
    }

    public function zoneTitles(string $versionId): array
    {
        return PricingZoneRecord::query()->toBase()->where('zone_set_version_id', $versionId)->orderBy('title')->get(['code', 'title'])->map(fn($z) => (array) $z)->all();
    }

    public function visibleGroup(string $hqId, string $groupId, bool $locking): ?object
    {
        if ($locking) {
            DB::table('hq_tenants')->where('hq_id', $hqId)->lockForUpdate()->first();
        }
        $q = PricingZoneSetRecord::query()->toBase()->where('pricing_zone_set_id', $groupId)->where(fn($q) => $q->where('hq_id', $hqId)->orWhereNull('hq_id'));
        if ($locking) {
            $q->lockForUpdate();
        }
        return $q->first();
    }

    public function currentVersion(string $groupId, \DateTimeInterface $at): ?object
    {
        return PricingZoneSetVersionRecord::query()->toBase()->where('pricing_zone_set_id', $groupId)->where('status', 'PUBLISHED')->where('valid_from', '<=', $at)->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $at))->orderByDesc('version_number')->first();
    }

    public function zoneCodes(string $versionId): array
    {
        return PricingZoneRecord::query()->toBase()->where('zone_set_version_id', $versionId)->pluck('code')->all();
    }

    public function members(string $versionId): array
    {
        return PricingZoneMemberRecord::query()->toBase()->from('pricing_zone_members as m')->join('pricing_zones as z', 'z.pricing_zone_id', '=', 'm.pricing_zone_id')->where('z.zone_set_version_id', $versionId)->get()->all();
    }
}
