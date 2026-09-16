<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Data\Page;
use Modules\Pricing\Application\Repositories\PricingRepository;
use Modules\Pricing\Infrastructure\Persistence\PricingStorageMap;

final class EloquentPricingRepository implements PricingRepository
{
    public function listTariffs(string $hqId, array $filters): Page
    {
        $query = PricingStorageMap::query('tariff_families')->from('tariff_families as f')->where(fn($q) => $q->whereNull('f.hq_id')->orWhere('f.hq_id', $hqId))->select(['f.*'])->selectSub(PricingStorageMap::query('tariff_versions')->from('tariff_versions as v')->select('v.status')->whereColumn('v.tariff_family_id', 'f.tariff_family_id')->orderByDesc('v.version_number')->limit(1), 'latest_status')->selectSub(PricingStorageMap::query('tariff_versions')->from('tariff_versions as v')->select('v.version_number')->whereColumn('v.tariff_family_id', 'f.tariff_family_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_number');
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $query->where(fn($q) => $q->where('f.code', 'like', $search)->orWhere('f.title', 'like', $search));
        }
        $page = $query->orderBy('f.code')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function listZoneSets(string $hqId, array $filters): Page
    {
        $query = PricingStorageMap::query('pricing_zone_sets')->from('pricing_zone_sets as s')->where(fn($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $hqId))->select(['s.*'])->selectSub(PricingStorageMap::query('pricing_zone_set_versions')->from('pricing_zone_set_versions as v')->select('v.status')->whereColumn('v.pricing_zone_set_id', 's.pricing_zone_set_id')->orderByDesc('v.version_number')->limit(1), 'latest_status')->selectSub(PricingStorageMap::query('pricing_zone_set_versions')->from('pricing_zone_set_versions as v')->select('v.version_number')->whereColumn('v.pricing_zone_set_id', 's.pricing_zone_set_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_number');
        if (($filters['search'] ?? '') !== '') {
            $query->where(fn($q) => $q->where('s.code', 'like', '%' . addcslashes((string) $filters['search'], '%_\\') . '%')->orWhere('s.title', 'like', '%' . addcslashes((string) $filters['search'], '%_\\') . '%'));
        }
        $page = $query->orderBy('s.code')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function listZoneSetVersionReferences(string $hqId, array $filters): Page
    {
        $includeVersionId = (string) ($filters['include_version_id'] ?? '');
        $query = PricingStorageMap::query('pricing_zone_set_versions')->from('pricing_zone_set_versions as v')->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')->where(fn($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $hqId));
        $search = ($filters['search'] ?? '') === '' ? null : '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
        $query->where(function ($q) use ($includeVersionId, $search): void {
            $q->where(function ($published) use ($search): void {
                $published->where('v.status', 'PUBLISHED');
                if ($search !== null) {
                    $published->where(fn($match) => $match->where('s.code', 'like', $search)->orWhere('s.title', 'like', $search));
                }
            });
            if ($includeVersionId !== '') {
                $q->orWhere('v.zone_set_version_id', $includeVersionId);
            }
        });
        $page = $query->select([
            'v.zone_set_version_id',
            'v.pricing_zone_set_id',
            'v.version_number',
            'v.status',
            'v.valid_from',
            'v.valid_to',
            's.code',
            's.title',
            's.purpose',
        ])->orderByRaw('v.zone_set_version_id = ? DESC', [$includeVersionId ?: '00000000-0000-0000-0000-000000000000'])->orderBy('s.title')->orderByDesc('v.version_number')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 50))), page: max(1, (int) ($filters['page'] ?? 1)));
        $page->setCollection($page->getCollection()->map(function ($row): array {
            $reference = (array) $row;
            $reference['zones'] = PricingStorageMap::query('pricing_zones')->where('zone_set_version_id', $row->zone_set_version_id)->orderBy('title')->get(['pricing_zone_id', 'code', 'title', 'remote_area', 'rank'])->map(fn($zone) => [...(array) $zone, 'remote_area' => (bool) $zone->remote_area])->all();
            return $reference;
        }));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function auditEvents(string $hqId, array $filters): Page
    {
        $query = DB::table('audit_events')->where('hq_id', $hqId)->where('action_key', 'like', 'PRICING_%');
        if (!empty($filters['target_id'])) {
            $query->where('target_id', $filters['target_id']);
        }
        $page = $query->orderByDesc('created_at')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function serviceTariffReferences(string $hqId, \DateTimeInterface $at): array
    {
        return PricingStorageMap::query('tariff_families')->from('tariff_families as f')->join('tariff_versions as v', 'v.tariff_family_id', '=', 'f.tariff_family_id')->leftJoin('pricing_zone_set_versions as z', 'z.zone_set_version_id', '=', 'v.zone_set_version_id')->join('pricing_charge_types as c', 'c.charge_type_id', '=', 'f.service_charge_type_id')->where('f.tariff_kind', 'SERVICE')->where(fn($q) => $q->whereNull('f.hq_id')->orWhere('f.hq_id', $hqId))->where('v.status', 'PUBLISHED')->where('v.valid_from', '<=', $at)->where(fn($q) => $q->whereNull('v.valid_to')->orWhere('v.valid_to', '>', $at))->orderByDesc('v.version_number')->get([
            'f.tariff_family_id',
            'f.title',
            'f.code',
            'f.service_charge_type_id',
            'c.code as charge_code',
            'v.tariff_version_id',
            'v.version_number',
            'z.pricing_zone_set_id',
        ])->unique('tariff_family_id')->values()->all();
    }

    public function chargeTypes(): array
    {
        return PricingStorageMap::query('pricing_charge_types')->orderBy('code')->get()->all();
    }

    public function identityVisible(string $hqId, string $kind, string $identityId): bool
    {
        [$identityTable, $versionTable, $parentId, $versionId] = PricingStorageMap::map($kind);
        return PricingStorageMap::query($identityTable)->where($parentId, $identityId)->where(fn($q) => $q->whereNull('hq_id')->orWhere('hq_id', $hqId))->exists();
    }

    public function history(string $kind, string $identityId): array
    {
        [$identityTable, $versionTable, $parentId, $versionId] = PricingStorageMap::map($kind);
        return PricingStorageMap::query($versionTable)->where($parentId, $identityId)->orderByDesc('version_number')->pluck($versionId)->all();
    }

    public function tenantIdentityExists(string $hqId, string $kind, string $identityId): bool
    {
        [$identityTable, $versionTable, $parentId, $versionId] = PricingStorageMap::map($kind);
        return PricingStorageMap::query($identityTable)->where([$parentId => $identityId, 'hq_id' => $hqId])->exists();
    }

    public function lockLatestVersion(string $kind, string $identityId): ?object
    {
        [$identityTable, $versionTable, $parentId, $versionId] = PricingStorageMap::map($kind);
        return PricingStorageMap::query($versionTable)->where($parentId, $identityId)->orderByDesc('version_number')->lockForUpdate()->first();
    }

    public function hasUnpublishedSuccessor(string $kind, string $identityId): bool
    {
        [$identityTable, $versionTable, $parentId, $versionId] = PricingStorageMap::map($kind);
        return PricingStorageMap::query($versionTable)->where($parentId, $identityId)->whereIn('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED'])->exists();
    }

    public function insertVersion(string $kind, array $attributes): void
    {
        [$identityTable, $versionTable, $parentId, $versionId] = PricingStorageMap::map($kind);
        PricingStorageMap::query($versionTable)->insert($attributes);
    }

    public function unorderedRules(string $versionId): array
    {
        return PricingStorageMap::query('tariff_rate_rules')->where('tariff_version_id', $versionId)->get()->all();
    }

    public function chargeType(string $id): ?object
    {
        return PricingStorageMap::query('pricing_charge_types')->where('charge_type_id', $id)->first();
    }

    public function lockZoneVersion(string $hqId, string $versionId): ?object
    {
        return PricingStorageMap::query('pricing_zone_set_versions')->where('zone_set_version_id', $versionId)->where('hq_id', $hqId)->lockForUpdate()->first();
    }

    public function lockTariffVersion(string $hqId, string $versionId): ?object
    {
        return PricingStorageMap::query('tariff_versions')->where('tariff_version_id', $versionId)->where('hq_id', $hqId)->lockForUpdate()->first();
    }

    public function updateZoneVersion(string $versionId, array $changes): void
    {
        PricingStorageMap::query('pricing_zone_set_versions')->where('zone_set_version_id', $versionId)->update($changes);
    }

    public function updateTariffVersion(string $versionId, array $changes): void
    {
        PricingStorageMap::query('tariff_versions')->where('tariff_version_id', $versionId)->update($changes);
    }

    public function offeringHasPublishedSuccessor(string $reference): bool
    {
        return DB::table('service_offering_versions as old')->join('service_offering_versions as current', 'current.service_offering_id', '=', 'old.service_offering_id')->join('service_offerings as identity', 'identity.service_offering_id', '=', 'old.service_offering_id')->where('old.service_offering_version_id', $reference)->where('current.status', 'PUBLISHED')->where('identity.status', 'ACTIVE')->exists();
    }

    public function lockTenant(string $hqId): void
    {
        DB::table('hq_tenants')->where('hq_id', $hqId)->lockForUpdate()->first();
    }

    public function lockVersion(string $hqId, string $kind, string $versionId): ?object
    {
        [, $table, , $id] = PricingStorageMap::map($kind);
        return PricingStorageMap::query($table)->where($id, $versionId)->where('hq_id', $hqId)->lockForUpdate()->first();
    }

    public function updateVersion(string $kind, string $versionId, array $changes): void
    {
        [, $table, , $id] = PricingStorageMap::map($kind);
        PricingStorageMap::query($table)->where($id, $versionId)->update($changes);
    }

    public function lockSimulationTariff(string $hqId, string $versionId): ?object
    {
        return PricingStorageMap::query('tariff_versions')->from('tariff_versions as v')->join('tariff_families as f', 'f.tariff_family_id', '=', 'v.tariff_family_id')->where('v.tariff_version_id', $versionId)->where('v.hq_id', $hqId)->select(['v.*', 'f.currency', 'f.code as tariff_code', 'f.title as tariff_title', 'f.tariff_kind'])->lockForUpdate()->first();
    }

    public function quoteForRequest(string $hqId, string $userId, string $idempotencyKey): ?object
    {
        return PricingStorageMap::query('pricing_quotes')->where(['hq_id' => $hqId, 'requested_by' => $userId, 'idempotency_key' => $idempotencyKey])->first();
    }

    public function eligibleTariff(string $hqId, array $offeringReferences, \DateTimeInterface $asOf): ?object
    {
        return PricingStorageMap::query('tariff_versions')->from('tariff_versions as v')->join('tariff_families as f', 'f.tariff_family_id', '=', 'v.tariff_family_id')->where('f.tariff_kind', 'FREIGHT')->where('f.purpose', 'SALES')->where('f.currency', 'IRR')->where(fn($q) => $q->whereNull('f.hq_id')->orWhere('f.hq_id', $hqId))->whereExists(fn($q) => $q->selectRaw('1')->from('tariff_rate_rules as eligible_rule')->whereColumn('eligible_rule.tariff_version_id', 'v.tariff_version_id')->whereIn('eligible_rule.service_offering_version_id', $offeringReferences))->where(function ($q) use ($hqId): void {
            $q->where(fn($scope) => $scope->where('f.scope_type', 'PLATFORM')->whereNull('f.hq_id'))->orWhere(fn($scope) => $scope->where('f.scope_type', 'TENANT')->where(fn($value) => $value->whereNull('f.scope_value')->orWhere('f.scope_value', $hqId)));
        })->where('v.status', 'PUBLISHED')->where('v.valid_from', '<=', $asOf)->where(fn($q) => $q->whereNull('v.valid_to')->orWhere('v.valid_to', '>', $asOf))->whereNotExists(fn($q) => $q->selectRaw('1')->from('tariff_versions as newer')->whereColumn('newer.tariff_family_id', 'v.tariff_family_id')->whereColumn('newer.version_number', '>', 'v.version_number')->where('newer.status', 'PUBLISHED')->where('newer.valid_from', '<=', $asOf)->where(fn($end) => $end->whereNull('newer.valid_to')->orWhere('newer.valid_to', '>', $asOf)))->orderByDesc('v.is_default')->orderBy('f.priority')->orderByRaw("FIELD(f.scope_type, 'CONTRACT', 'CUSTOMER', 'SEGMENT', 'TENANT', 'PLATFORM')")->orderBy('f.code')->orderByDesc('v.version_number')->select(['v.*', 'f.currency', 'f.code as tariff_code', 'f.title as tariff_title', 'f.tariff_kind'])->first();
    }

    public function zones(string $versionId): array
    {
        return PricingStorageMap::query('pricing_zones')->where('zone_set_version_id', $versionId)->get()->all();
    }

    public function freightRules(string $tariffId, array $offeringReferences, array $optionReferences): array
    {
        return PricingStorageMap::query('tariff_rate_rules')->from('tariff_rate_rules as r')->join('pricing_charge_types as c', 'c.charge_type_id', '=', 'r.charge_type_id')->leftJoin('pricing_zones as origin_rule_zone', 'origin_rule_zone.pricing_zone_id', '=', 'r.origin_zone_id')->leftJoin('pricing_zones as destination_rule_zone', 'destination_rule_zone.pricing_zone_id', '=', 'r.destination_zone_id')->where('r.tariff_version_id', $tariffId)->whereIn('r.service_offering_version_id', $offeringReferences)->where(fn($q) => $q->whereNull('r.service_option_version_id')->orWhereIn('r.service_option_version_id', $optionReferences))->select([
            'r.*',
            'origin_rule_zone.code as origin_code',
            'destination_rule_zone.code as destination_code',
            'c.code as charge_type_code',
            'c.category',
            'c.accounting_mapping_key',
            'c.code as title',
            DB::raw('COALESCE(r.taxable, c.taxable) as taxable'),
        ])->get()->all();
    }

    public function serviceRules(string $tariffId, ?string $cellId): array
    {
        return PricingStorageMap::query('tariff_rate_rules')->from('tariff_rate_rules as r')->join('pricing_charge_types as c', 'c.charge_type_id', '=', 'r.charge_type_id')->where('r.tariff_version_id', $tariffId)->where('r.matrix_cell_id', $cellId)->select([
            'r.*',
            'c.code as charge_type_code',
            'c.code as title',
            'c.category',
            'c.accounting_mapping_key',
            DB::raw('COALESCE(r.taxable,c.taxable) as taxable'),
        ])->get()->all();
    }

    public function quote(string $hqId, string $quoteId): ?object
    {
        return PricingStorageMap::query('pricing_quotes')->where(['quote_id' => $quoteId, 'hq_id' => $hqId])->first();
    }

    public function quoteLinesWithCategory(string $quoteId): array
    {
        return PricingStorageMap::query('pricing_quote_lines')->from('pricing_quote_lines as l')->join('pricing_charge_types as c', 'c.charge_type_id', '=', 'l.charge_type_id')->where('l.quote_id', $quoteId)->orderBy('l.line_number')->get(['l.*', 'c.category'])->all();
    }

    public function rejectQuote(string $hqId, string $quoteId, \DateTimeInterface $at): void
    {
        PricingStorageMap::query('pricing_quotes')->where(['quote_id' => $quoteId, 'hq_id' => $hqId, 'status' => 'OFFERED'])->update(['status' => 'REJECTED', 'updated_at' => $at]);
    }

    public function snapshotForAcceptance(string $hqId, string $userId, string $idempotencyKey): ?object
    {
        return PricingStorageMap::query('pricing_snapshots')->where(['hq_id' => $hqId, 'accepted_by' => $userId, 'acceptance_idempotency_key' => $idempotencyKey])->first();
    }

    public function consignmentExists(string $hqId, string $objectId): bool
    {
        return DB::table('consignments')->where(['hq_id' => $hqId, 'consignment_id' => $objectId])->exists();
    }

    public function lockQuote(string $hqId, string $quoteId): ?object
    {
        return PricingStorageMap::query('pricing_quotes')->where(['quote_id' => $quoteId, 'hq_id' => $hqId])->lockForUpdate()->first();
    }

    public function quoteLines(string $quoteId): array
    {
        return PricingStorageMap::query('pricing_quote_lines')->where('quote_id', $quoteId)->orderBy('line_number')->get()->all();
    }

    public function chargeCategories(array $chargeIds): array
    {
        return PricingStorageMap::query('pricing_charge_types')->whereIn('charge_type_id', $chargeIds)->pluck('category', 'charge_type_id')->all();
    }

    public function acceptQuote(string $quoteId, \DateTimeInterface $now): void
    {
        PricingStorageMap::query('pricing_quotes')->where('quote_id', $quoteId)->update(['status' => 'ACCEPTED', 'accepted_at' => $now, 'updated_at' => $now]);
    }

    public function tariffVersion(string $hqId, string $versionId): ?object
    {
        return PricingStorageMap::query('tariff_versions')->from('tariff_versions as v')->join('tariff_families as f', 'f.tariff_family_id', '=', 'v.tariff_family_id')->where('v.tariff_version_id', $versionId)->where(fn($q) => $q->whereNull('f.hq_id')->orWhere('f.hq_id', $hqId))->select([
            'v.*',
            'f.code',
            'f.title',
            'f.purpose',
            'f.currency',
            'f.scope_type',
            'f.scope_value',
            'f.priority',
            'f.tariff_kind',
            'f.service_charge_type_id',
        ])->first();
    }

    public function orderedRules(string $versionId): array
    {
        return PricingStorageMap::query('tariff_rate_rules')->where('tariff_version_id', $versionId)->orderBy('priority')->get()->all();
    }

    public function zoneVersion(string $hqId, string $versionId): ?object
    {
        return PricingStorageMap::query('pricing_zone_set_versions')->from('pricing_zone_set_versions as v')->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')->where('v.zone_set_version_id', $versionId)->where(fn($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $hqId))->select(['v.*', 's.code', 's.title', 's.purpose'])->first();
    }

    public function zoneMembers(string $zoneId): array
    {
        return PricingStorageMap::query('pricing_zone_members')->from('pricing_zone_members as m')->leftJoin('cities as c', 'c.city_id', '=', 'm.city_id')->leftJoin('provinces as p', DB::raw('p.province_id'), '=', DB::raw('COALESCE(c.province_id, m.province_id)'))->where('m.pricing_zone_id', $zoneId)->select([
            'm.zone_member_id',
            'm.pricing_zone_id',
            'm.member_type',
            'm.reference_value',
            'm.city_id',
            DB::raw('COALESCE(c.province_id, m.province_id) as province_id'),
            'm.geometry',
            'm.range_end',
            'm.precedence',
            'c.name_fa as city_name_fa',
            'c.legacy_city_code',
            'p.name_fa as province_name_fa',
            'p.legacy_province_code',
        ])->get()->all();
    }

    public function snapshot(string $snapshotId): ?object
    {
        return PricingStorageMap::query('pricing_snapshots')->where('pricing_snapshot_id', $snapshotId)->first();
    }

    public function chargeLines(string $snapshotId): array
    {
        return PricingStorageMap::query('pricing_charge_lines')->where('pricing_snapshot_id', $snapshotId)->orderBy('line_number')->get()->all();
    }

    public function savedPostalMembers(string $versionId): array
    {
        return PricingStorageMap::query('pricing_zone_members')->from('pricing_zone_members as m')->join('pricing_zones as z', 'z.pricing_zone_id', '=', 'm.pricing_zone_id')->where('z.zone_set_version_id', $versionId)->where('m.member_type', 'POSTAL_RANGE')->get(['z.pricing_zone_id', 'm.reference_value', 'm.range_end'])->all();
    }

    public function zoneIdsByCode(string $versionId): array
    {
        return PricingStorageMap::query('pricing_zones')->where('zone_set_version_id', $versionId)->pluck('pricing_zone_id', 'code')->all();
    }

    public function deleteZones(string $versionId): void
    {
        PricingStorageMap::query('pricing_zones')->where('zone_set_version_id', $versionId)->delete();
    }

    public function activeProvince(string $provinceId): bool
    {
        return DB::table('provinces')->where('province_id', $provinceId)->where('is_active', true)->exists();
    }

    public function deleteRules(string $versionId): void
    {
        PricingStorageMap::query('tariff_rate_rules')->where('tariff_version_id', $versionId)->delete();
    }

    public function zoneCodesById(?string $versionId): array
    {
        return PricingStorageMap::query('pricing_zones')->where('zone_set_version_id', $versionId)->pluck('code', 'pricing_zone_id')->all();
    }

    public function zoneGroup(string $configuredVersionId): ?string
    {
        return PricingStorageMap::query('pricing_zone_set_versions')->where('zone_set_version_id', $configuredVersionId)->value('pricing_zone_set_id');
    }

    public function effectiveZoneVersions(string $identityId, \DateTimeInterface $asOf): array
    {
        return PricingStorageMap::query('pricing_zone_set_versions')->where('pricing_zone_set_id', $identityId)->where('status', 'PUBLISHED')->where('valid_from', '<=', $asOf)->where(fn($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', $asOf))->orderByDesc('version_number')->pluck('zone_set_version_id')->all();
    }

    public function activeCity(string $cityId): ?object
    {
        return DB::table('cities')->where('city_id', $cityId)->where('is_active', true)->first(['city_id', 'province_id']);
    }

    public function citiesByName(string $normalizedName): array
    {
        return DB::table('cities')->where('normalized_name', $normalizedName)->where('is_active', true)->get(['city_id', 'province_id'])->all();
    }

    public function zoneVersionVisible(string $hqId, string $zoneSetVersionId): bool
    {
        return PricingStorageMap::query('pricing_zone_set_versions')->from('pricing_zone_set_versions as v')->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')->where('v.zone_set_version_id', $zoneSetVersionId)->where(fn($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $hqId))->exists();
    }

    public function zoneIds(string $zoneSetVersionId): array
    {
        return PricingStorageMap::query('pricing_zones')->where('zone_set_version_id', $zoneSetVersionId)->pluck('pricing_zone_id')->all();
    }

    public function hasVersionOverlap(string $kind, array $version): bool
    {
        [, $table, $parentId, $versionId] = PricingStorageMap::map($kind);
        if (!$version['valid_from']) {
            return false;
        }
        $versionId = $table === 'tariff_versions' ? 'tariff_version_id' : 'zone_set_version_id';
        $query = PricingStorageMap::query($table)->where($parentId, $version[$parentId])->where($versionId, '!=', $version[$versionId])->whereIn('status', ['APPROVED', 'PUBLISHED'])->where('version_number', '>=', $version['version_number'])->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $version['valid_from']));
        if ($version['valid_to']) {
            $query->where('valid_from', '<', $version['valid_to']);
        }
        return $query->exists();
    }

    public function defaultConflict(array $version, string $hqId): bool
    {
        if ($version['tariff_kind'] !== 'FREIGHT') {
            return true;
        }
        $services = PricingStorageMap::query('tariff_rate_rules')->from('tariff_rate_rules as r')->join('service_offering_versions as o', 'o.service_offering_version_id', '=', 'r.service_offering_version_id')->where('r.tariff_version_id', $version['tariff_version_id'])->pluck('o.service_offering_id')->unique()->all();
        $query = PricingStorageMap::query('tariff_versions')->from('tariff_versions as v')->join('tariff_families as f', 'f.tariff_family_id', '=', 'v.tariff_family_id')->where('f.hq_id', $hqId)->where('f.tariff_family_id', '!=', $version['tariff_family_id'])->where('v.is_default', true)->where('v.status', 'PUBLISHED')->whereExists(fn($q) => $q->selectRaw('1')->from('tariff_rate_rules as r')->join('service_offering_versions as o', 'o.service_offering_version_id', '=', 'r.service_offering_version_id')->whereColumn('r.tariff_version_id', 'v.tariff_version_id')->whereIn('o.service_offering_id', $services));
        if ($version['valid_to']) {
            $query->where('v.valid_from', '<', CarbonImmutable::parse($version['valid_to']));
        }
        if ($version['valid_from']) {
            $query->where(fn($q) => $q->whereNull('v.valid_to')->orWhere('v.valid_to', '>', CarbonImmutable::parse($version['valid_from'])));
        }
        return $query->exists();
    }

    public function insertChargeType(array $attributes): void
    {
        PricingStorageMap::query('pricing_charge_types')->insert($attributes);
    }

    public function insertZoneSet(array $attributes): void
    {
        PricingStorageMap::query('pricing_zone_sets')->insert($attributes);
    }

    public function insertZoneVersion(array $attributes): void
    {
        PricingStorageMap::query('pricing_zone_set_versions')->insert($attributes);
    }

    public function insertTariffFamily(array $attributes): void
    {
        try {
            PricingStorageMap::query('tariff_families')->insert($attributes);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $error) {
            throw new \Modules\Pricing\Domain\TariffCodeConflict(previous: $error);
        }
    }

    public function insertTariffVersion(array $attributes): void
    {
        PricingStorageMap::query('tariff_versions')->insert($attributes);
    }

    public function insertQuote(array $attributes): void
    {
        PricingStorageMap::query('pricing_quotes')->insert($attributes);
    }

    public function insertSnapshot(array $attributes): void
    {
        PricingStorageMap::query('pricing_snapshots')->insert($attributes);
    }

    public function insertChargeLine(array $attributes): void
    {
        PricingStorageMap::query('pricing_charge_lines')->insert($attributes);
    }

    public function insertZone(array $attributes): void
    {
        PricingStorageMap::query('pricing_zones')->insert($attributes);
    }

    public function insertZoneMember(array $attributes): void
    {
        PricingStorageMap::query('pricing_zone_members')->insert($attributes);
    }

    public function insertRateRule(array $attributes): void
    {
        PricingStorageMap::query('tariff_rate_rules')->insert($attributes);
    }

    public function insertQuoteLine(array $attributes): void
    {
        PricingStorageMap::query('pricing_quote_lines')->insert($attributes);
    }
}
