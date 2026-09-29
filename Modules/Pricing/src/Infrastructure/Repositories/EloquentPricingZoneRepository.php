<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Pricing\Application\Dto\PricingZoneVersionSearchDto;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneMemberRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

final class EloquentPricingZoneRepository implements PricingZoneRepositoryInterface
{
    /** Rows written per statement, so a wide Zone Set never builds one oversized query. */
    private const BATCH_SIZE = 100;

    /** The graph a Zone version response renders. */
    private const VERSION_RELATIONS = ['zoneSet', 'zones.members.city.province', 'zones.members.province'];

    public function createZoneSet(array $attributes): string
    {
        return (string) PricingZoneSetRecord::query()->forceCreate($attributes)->getKey();
    }

    public function createZoneSetVersion(array $attributes): string
    {
        return (string) PricingZoneSetVersionRecord::query()->forceCreate($attributes)->getKey();
    }

    public function versionVisible(?string $hqId, string $versionId): bool
    {
        return PricingZoneSetVersionRecord::query()->where('zone_set_version_id', $versionId)
            ->whereHas('zoneSet', fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))->exists();
    }

    public function lockTenantVersion(?string $hqId, string $versionId): ?PricingZoneSetVersionRecord
    {
        return PricingZoneSetVersionRecord::query()->where(['hq_id' => $hqId, 'zone_set_version_id' => $versionId])->lockForUpdate()->first();
    }

    public function findVisibleVersionDetail(?string $hqId, string $versionId): ?PricingZoneSetVersionRecord
    {
        return $this->visibleVersions($hqId)->where('zone_set_version_id', $versionId)->first();
    }

    public function visibleVersionDetails(?string $hqId, array $versionIds): Collection
    {
        return $this->visibleVersions($hqId)->whereIn('zone_set_version_id', $versionIds)->get();
    }

    public function findVisibleVersionWithZones(?string $hqId, string $versionId): ?PricingZoneSetVersionRecord
    {
        return PricingZoneSetVersionRecord::query()->where('zone_set_version_id', $versionId)
            ->whereHas('zoneSet', fn ($zoneSet) => $zoneSet->whereNull('hq_id')->orWhere('hq_id', $hqId))->with('zones')->first();
    }

    public function paginateZoneSets(?string $hqId, int $page, int $pageSize, string $search): LengthAwarePaginator
    {
        $query = PricingZoneSetRecord::query()->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->with('latestVersion');
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($match) => $match->where('code', 'like', $term)->orWhere('title', 'like', $term));
        }

        return $query->orderBy('code')->paginate($pageSize, page: $page);
    }

    public function paginateVersionReferences(?string $hqId, PricingZoneVersionSearchDto $filter): LengthAwarePaginator
    {
        $query = PricingZoneSetVersionRecord::query()
            ->whereHas('zoneSet', fn ($zoneSet) => $zoneSet->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->with(['zoneSet', 'zones' => fn ($zones) => $zones->orderBy('title')]);
        $query->where(function ($visible) use ($filter): void {
            $visible->where(function ($published) use ($filter): void {
                $published->where('status', VersionLifecycleStatus::Published->value);
                if ($filter->search !== '') {
                    $search = '%'.addcslashes($filter->search, '%_\\').'%';
                    $published->whereHas('zoneSet', fn ($zoneSet) => $zoneSet->where('code', 'like', $search)->orWhere('title', 'like', $search));
                }
            });
            if ($filter->includeVersionId !== null && $filter->includeVersionId !== '') {
                $visible->orWhere('zone_set_version_id', $filter->includeVersionId);
            }
        });
        if ($filter->includeVersionId !== null && $filter->includeVersionId !== '') {
            // A selected draft stays first without changing tenant or search visibility.
            $query->orderByRaw('id = ? DESC', [$filter->includeVersionId]);
        }

        return $query->orderBy(PricingZoneSetRecord::query()->select('title')->whereColumn('pricing_zone_sets.id', 'pricing_zone_set_versions.pricing_zone_set_id'))
            ->orderByDesc('version_number')->paginate(min(100, max(1, $filter->pageSize)), page: max(1, $filter->page));
    }

    public function publishedZoneSets(string $hqId, DateTimeInterface $at): Collection
    {
        return PricingZoneSetRecord::query()->where(fn ($scope) => $scope->where('hq_id', $hqId)->orWhereNull('hq_id'))
            ->whereHas('versions', fn ($versions) => $versions->publishedAt($at))
            ->with(['versions' => fn ($versions) => $versions->publishedAt($at)->orderByDesc('version_number'), 'versions.zones' => fn ($zones) => $zones->orderBy('title')])
            ->orderBy('title')->get();
    }

    public function findVisibleZoneSet(string $hqId, string $zoneSetId, bool $locking): ?PricingZoneSetRecord
    {
        return PricingZoneSetRecord::query()->where('pricing_zone_set_id', $zoneSetId)
            ->where(fn ($scope) => $scope->where('hq_id', $hqId)->orWhereNull('hq_id'))
            ->when($locking, fn ($query) => $query->lockForUpdate())->first();
    }

    public function visibleZoneSetsWithCurrentVersion(string $hqId, array $zoneSetIds, DateTimeInterface $at): Collection
    {
        return PricingZoneSetRecord::query()->whereIn('pricing_zone_set_id', array_unique($zoneSetIds))
            ->where(fn ($scope) => $scope->where('hq_id', $hqId)->orWhereNull('hq_id'))
            ->with(['versions' => fn ($versions) => $versions->publishedAt($at)->orderByDesc('version_number')->limit(1),
                'versions.zones.members'])->get()->keyBy('pricing_zone_set_id');
    }

    public function membersOfVersion(string $versionId): Collection
    {
        return PricingZoneMemberRecord::query()->whereHas('zone', fn ($zone) => $zone->where('zone_set_version_id', $versionId))
            ->with('zone')->orderBy('id')->get();
    }

    public function membersOfVersionByType(string $versionId, string $memberType): array
    {
        return PricingZoneMemberRecord::query()->where('member_type', $memberType)
            ->whereHas('zone', fn ($zone) => $zone->where('zone_set_version_id', $versionId))->get()->all();
    }

    public function zoneSetIdOfVersion(?string $versionId): ?string
    {
        $id = PricingZoneSetVersionRecord::query()->where('zone_set_version_id', $versionId)->value('pricing_zone_set_id');

        return $id === null ? null : (string) $id;
    }

    public function publishedVersionIdOfZoneSet(?string $zoneSetId, DateTimeInterface $asOf): ?string
    {
        $id = PricingZoneSetVersionRecord::query()->where('pricing_zone_set_id', $zoneSetId)
            ->publishedAt($asOf)->orderByDesc('version_number')->value('zone_set_version_id');

        return $id === null ? null : (string) $id;
    }

    public function publishedVersionsOfZoneSets(array $zoneSetIds, DateTimeInterface $asOf): Collection
    {
        return PricingZoneSetVersionRecord::query()->whereIn('pricing_zone_set_id', $zoneSetIds)
            ->where('status', VersionLifecycleStatus::Published->value)->where('valid_from', '<=', $asOf)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', $asOf))
            ->orderByDesc('version_number')->get(['pricing_zone_set_id', 'zone_set_version_id']);
    }

    public function zoneIdsOfVersion(string $versionId): array
    {
        return PricingZoneRecord::query()->where('zone_set_version_id', $versionId)->pluck('pricing_zone_id')->all();
    }

    public function zoneCodesOfVersion(?string $versionId): array
    {
        return PricingZoneRecord::query()->where('zone_set_version_id', $versionId)->pluck('code', 'pricing_zone_id')->all();
    }

    public function zoneRanksOfVersion(?string $versionId): array
    {
        return PricingZoneRecord::query()->where('zone_set_version_id', $versionId)->pluck('rank')->all();
    }

    public function zoneIdsByCode(string $versionId): array
    {
        return PricingZoneRecord::query()->where('zone_set_version_id', $versionId)->pluck('pricing_zone_id', 'code')->all();
    }

    public function deleteZonesOfVersion(string $versionId): void
    {
        PricingZoneRecord::query()->where('zone_set_version_id', $versionId)->delete();
    }

    public function insertZonesWithMembers(array $zones, array $members): void
    {
        $zoneRows = array_map(static fn (array $row): array => (new PricingZoneRecord)->forceFill($row)->getAttributes(), $zones);
        foreach (array_chunk($zoneRows, self::BATCH_SIZE) as $chunk) {
            PricingZoneRecord::query()->insert($chunk);
        }
        $ids = PricingZoneRecord::query()->whereIn('zone_set_version_id', array_unique(array_column($zones, 'zone_set_version_id')))->get()->keyBy(fn ($zone) => $zone->zone_set_version_id.'|'.$zone->code);
        $memberRows = array_map(static function (array $row) use ($ids): array {
            if (isset($row['_zone_code'])) {
                $row['pricing_zone_id'] = $ids[$row['_zone_version_id'].'|'.$row['_zone_code']]->getKey();
                unset($row['_zone_code'], $row['_zone_version_id']);
            }

            return (new PricingZoneMemberRecord)->forceFill($row)->getAttributes();
        }, $members);
        foreach (array_chunk($memberRows, self::BATCH_SIZE) as $chunk) {
            PricingZoneMemberRecord::query()->insert($chunk);
        }
    }

    public function zonesByVersion(array $versionIds): SupportCollection
    {
        return PricingZoneRecord::query()->whereIn('zone_set_version_id', $versionIds)->get()->groupBy('zone_set_version_id');
    }

    /** @return Builder<PricingZoneSetVersionRecord> */
    private function visibleVersions(?string $hqId): Builder
    {
        return PricingZoneSetVersionRecord::query()
            ->whereHas('zoneSet', fn ($zoneSet) => $zoneSet->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->with(self::VERSION_RELATIONS);
    }
}
