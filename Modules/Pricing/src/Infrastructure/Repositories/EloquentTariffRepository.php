<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Foundation\Domain\Enums\Currency;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Domain\Enums\PricingPurpose;
use Modules\Pricing\Domain\Enums\TariffKind;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffFamilyRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffRateRuleRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffServiceAttachmentRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final class EloquentTariffRepository implements TariffRepositoryInterface
{
    /** Rows written per statement, so a wide tariff never builds one oversized query. */
    private const BATCH_SIZE = 100;

    /** Relations a Tariff version response renders. */
    private const VERSION_RELATIONS = ['family', 'rules', 'serviceAttachments'];

    /** Versions that still describe a price a live Tariff may be attached to. */
    public function createFamily(array $attributes): string
    {
        return (string) TariffFamilyRecord::query()->forceCreate($attributes)->getKey();
    }

    public function createVersion(array $attributes): string
    {
        return (string) TariffVersionRecord::query()->forceCreate($attributes)->getKey();
    }

    public function lockTenantVersion(?string $hqId, string $versionId): ?TariffVersionRecord
    {
        return TariffVersionRecord::query()->where(['hq_id' => $hqId, 'tariff_version_id' => $versionId])->lockForUpdate()->first();
    }

    public function lockTenantDraftWithFamily(?string $hqId, string $versionId): ?TariffVersionRecord
    {
        return TariffVersionRecord::query()->where(['hq_id' => $hqId, 'tariff_version_id' => $versionId])
            ->whereHas('family')->with('family')->lockForUpdate()->first();
    }

    public function findVisibleVersionDetail(?string $hqId, string $versionId): ?TariffVersionRecord
    {
        return $this->visibleVersions($hqId)->where('tariff_version_id', $versionId)->first();
    }

    public function visibleVersionDetails(?string $hqId, array $versionIds): Collection
    {
        return $this->visibleVersions($hqId)->whereIn('tariff_version_id', $versionIds)->get();
    }

    public function paginateFamilies(?string $hqId, int $page, int $pageSize, string $search): LengthAwarePaginator
    {
        $query = TariffFamilyRecord::query()->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->with('latestVersion');
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($match) => $match->where('code', 'like', $term)->orWhere('title', 'like', $term));
        }

        return $query->orderBy('code')->paginate($pageSize, page: $page);
    }

    public function findEligibleFreightVersion(string $hqId, array $offeringReferences, DateTimeInterface $asOf): ?TariffVersionRecord
    {
        // The join supplies deterministic family ordering; only version attributes are hydrated.
        return TariffVersionRecord::query()->select('tariff_versions.*')
            ->join('tariff_families as family', 'family.id', '=', 'tariff_versions.tariff_family_id')
            ->where('family.tariff_kind', TariffKind::Freight->value)->where('family.purpose', PricingPurpose::Sales->value)->where('family.currency', Currency::Irr->value)
            ->where(fn ($scope) => $scope->whereNull('family.hq_id')->orWhere('family.hq_id', $hqId))
            ->whereHas('rules', fn ($rules) => $rules->whereIn('service_offering_version_id', $offeringReferences))
            ->where(fn ($scopes) => $scopes
                ->where(fn ($platform) => $platform->where('family.scope_type', 'PLATFORM')->whereNull('family.hq_id'))
                ->orWhere(fn ($tenant) => $tenant->where('family.scope_type', 'TENANT')
                    ->where(fn ($value) => $value->whereNull('family.scope_value')->orWhere('family.scope_value', $hqId))))
            ->publishedAt($asOf)
            ->whereNotExists(TariffVersionRecord::query()->from('tariff_versions as newer')->select('newer.id')
                ->whereColumn('newer.tariff_family_id', 'tariff_versions.tariff_family_id')
                ->whereColumn('newer.version_number', '>', 'tariff_versions.version_number')
                ->where('newer.status', VersionLifecycleStatus::Published->value)->where('newer.valid_from', '<=', $asOf)
                ->where(fn ($end) => $end->whereNull('newer.valid_to')->orWhere('newer.valid_to', '>', $asOf)))
            ->orderByDesc('tariff_versions.is_default')->orderBy('family.priority')
            ->orderByRaw("CASE family.scope_type WHEN 'CONTRACT' THEN 1 WHEN 'CUSTOMER' THEN 2 WHEN 'SEGMENT' THEN 3 WHEN 'TENANT' THEN 4 WHEN 'PLATFORM' THEN 5 ELSE 0 END")
            ->orderBy('family.code')->orderByDesc('tariff_versions.version_number')->with('family')->first();
    }

    public function serviceFamiliesWithEffectiveVersion(array $familyIds, ?string $hqId, DateTimeInterface $effectiveAt): Collection
    {
        return TariffFamilyRecord::query()->whereIn('tariff_family_id', $familyIds)->where('tariff_kind', 'SERVICE')
            ->where(fn ($query) => $query->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->with(['chargeType', 'versions' => fn ($versions) => $versions->where('status', VersionLifecycleStatus::Published->value)
                ->where('valid_from', '<=', $effectiveAt)
                ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', $effectiveAt))
                ->orderByDesc('version_number')->limit(1)->with('zoneVersion')])
            ->get()->keyBy('tariff_family_id');
    }

    public function referenceableServiceFamilies(?string $hqId, DateTimeInterface $at): Collection
    {
        return TariffFamilyRecord::query()->where('tariff_kind', 'SERVICE')
            ->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->whereHas('chargeType')->whereHas('versions', fn ($versions) => $versions->publishedAt($at))
            ->with(['chargeType', 'versions' => fn ($versions) => $versions->publishedAt($at)->orderByDesc('version_number'), 'versions.zoneVersion'])
            ->get();
    }

    public function hasCompetingDefault(?string $hqId, string $tariffFamilyId, array $offeringIds, mixed $validFrom, mixed $validTo): bool
    {
        return TariffVersionRecord::query()->where('is_default', true)->where('status', VersionLifecycleStatus::Published->value)
            ->whereHas('family', fn ($family) => $family->where('hq_id', $hqId)->where('tariff_family_id', '!=', $tariffFamilyId))
            ->whereHas('rules.offeringVersion', fn ($offering) => $offering->whereIn('service_offering_id', $offeringIds))
            ->when($validTo !== null, fn ($query) => $query->where('valid_from', '<', $validTo))
            ->when($validFrom !== null, fn ($query) => $query->where(fn ($interval) => $interval->whereNull('valid_to')->orWhere('valid_to', '>', $validFrom)))
            ->exists();
    }

    public function offeringIdsOfVersionRules(string $versionId): array
    {
        return ServiceOfferingVersionRecord::query()->whereIn('id',
            TariffRateRuleRecord::query()->where('tariff_version_id', $versionId)->select('service_offering_version_id'))
            ->pluck('service_offering_id')->all();
    }

    public function deleteRules(string $versionId): void
    {
        TariffRateRuleRecord::query()->where('tariff_version_id', $versionId)->delete();
    }

    public function insertRules(array $rows): void
    {
        $attributes = array_map(static fn (array $row): array => (new TariffRateRuleRecord)->forceFill($row)->getAttributes(), $rows);
        foreach (array_chunk($attributes, self::BATCH_SIZE) as $chunk) {
            TariffRateRuleRecord::query()->insert($chunk);
        }
    }

    public function applicableRules(string $versionId, array $offeringReferences, array $optionReferences): Collection
    {
        return TariffRateRuleRecord::query()->where('tariff_version_id', $versionId)
            ->whereIn('service_offering_version_id', $offeringReferences)
            ->where(fn ($options) => $options->whereNull('service_option_version_id')->orWhereIn('service_option_version_id', $optionReferences))
            ->whereHas('chargeType')->with(['chargeType', 'originZone', 'destinationZone'])->get();
    }

    public function rulesByVersion(array $versionIds): SupportCollection
    {
        return TariffRateRuleRecord::query()->whereIn('tariff_version_id', $versionIds)
            ->whereHas('chargeType')->with('chargeType')->get()->groupBy('tariff_version_id');
    }

    public function serviceAttachmentIds(string $versionId): array
    {
        return TariffServiceAttachmentRecord::query()->where('tariff_version_id', $versionId)->pluck('service_tariff_family_id')->all();
    }

    public function deleteServiceAttachments(string $versionId): void
    {
        TariffServiceAttachmentRecord::query()->where('tariff_version_id', $versionId)->delete();
    }

    public function insertServiceAttachments(array $rows): void
    {
        TariffServiceAttachmentRecord::query()->insert($rows);
    }

    public function serviceFamilyBoundElsewhere(string $serviceFamilyId, ?string $zoneSetId, DateTimeInterface $at): bool
    {
        return TariffServiceAttachmentRecord::query()->where('service_tariff_family_id', $serviceFamilyId)
            ->whereHas('parentVersion', fn ($version) => $version->whereIn('status', VersionLifecycleStatus::valuesOf(VersionLifecycleStatus::effective()))
                ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', $at))
                ->whereHas('zoneVersion', fn ($zone) => $zone->where('pricing_zone_set_id', '!=', $zoneSetId)))
            ->exists();
    }

    /** @return Builder<TariffVersionRecord> */
    private function visibleVersions(?string $hqId): Builder
    {
        return TariffVersionRecord::query()
            ->whereHas('family', fn ($family) => $family->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->with(self::VERSION_RELATIONS);
    }
}
