<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Data\Page;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepository;
use Modules\ServiceCatalog\Infrastructure\Persistence\CatalogStorageMap;

final class EloquentCatalogRepository implements CatalogRepository
{
    public function listIdentities(string $hqId, string $resource, array $filters): Page
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        $query = CatalogStorageMap::query($identity)->from("{$identity} as i")->where(fn($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $hqId))->select(['i.*'])->selectSub(CatalogStorageMap::query($versions)->from("{$versions} as v")->select('v.status')->whereColumn("v.{$identityId}", "i.{$identityId}")->orderByDesc('v.version_number')->limit(1), 'latest_status')->selectSub(CatalogStorageMap::query($versions)->from("{$versions} as v")->select('v.version_number')->whereColumn("v.{$identityId}", "i.{$identityId}")->orderByDesc('v.version_number')->limit(1), 'latest_version_number')->selectSub(CatalogStorageMap::query($versions)->from("{$versions} as v")->select("v.{$versionId}")->whereColumn("v.{$identityId}", "i.{$identityId}")->orderByDesc('v.version_number')->limit(1), 'latest_version_id')->selectSub(CatalogStorageMap::query($versions)->from("{$versions} as v")->select('v.labels')->whereColumn("v.{$identityId}", "i.{$identityId}")->orderByDesc('v.version_number')->limit(1), 'labels');
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $query->where('i.code', 'like', $search);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('i.status', $filters['status']);
        }
        $page = $query->orderBy('i.code')->paginate(perPage: min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function listPublishedVersions(string $hqId, string $resource, array $filters): Page
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        $includeVersionIds = array_values(array_unique(array_map('strval', (array) ($filters['include_version_ids'] ?? []))));
        $includeVersionIds = array_map(fn($id) => CatalogStorageMap::query($versions)->where($identityId, $id)->orderByDesc('version_number')->value($versionId) ?? $id, $includeVersionIds);
        $query = CatalogStorageMap::query($versions)->from("{$versions} as v")->join("{$identity} as i", "i.{$identityId}", '=', "v.{$identityId}")->where(fn($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $hqId));
        $search = ($filters['search'] ?? '') === '' ? null : '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
        $query->where(function ($available) use ($includeVersionIds, $search, $versionId): void {
            $available->where(function ($published) use ($search): void {
                $published->where('v.status', 'PUBLISHED')->where('i.status', 'ACTIVE');
                if ($search !== null) {
                    $published->where(fn($match) => $match->where('i.code', 'like', $search)->orWhere('v.labels', 'like', $search));
                }
            });
            if ($includeVersionIds !== []) {
                $available->orWhereIn("v.{$versionId}", $includeVersionIds);
            }
        });
        $page = $query->select(['v.*', 'i.code', 'i.status as identity_status'])->orderBy('i.code')->orderByDesc('v.version_number')->paginate(perPage: min(100, max(1, (int) ($filters['page_size'] ?? 100))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function auditEvents(string $hqId, array $filters): Page
    {
        $query = DB::table('audit_events')->where('hq_id', $hqId)->where('action_key', 'like', 'SERVICE_CATALOG_%');
        if (!empty($filters['target_id'])) {
            $query->where('target_id', $filters['target_id']);
        }
        $page = $query->orderByDesc('created_at')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function lockLatestVersion(string $resource, string $identityIdValue): ?object
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($identityId, $identityIdValue)->orderByDesc('version_number')->lockForUpdate()->first();
    }

    public function hasUnpublishedSuccessor(string $resource, string $identityIdValue): bool
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($identityId, $identityIdValue)->whereIn('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED'])->exists();
    }

    public function lockVersion(string $hqId, string $resource, string $versionIdValue): ?object
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($versionId, $versionIdValue)->where('hq_id', $hqId)->lockForUpdate()->first();
    }

    public function insertIdentity(string $resource, array $attributes): void
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        CatalogStorageMap::query($identity)->insert($attributes);
    }

    public function insertVersion(string $resource, array $attributes): void
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        CatalogStorageMap::query($versions)->insert($attributes);
    }

    public function updateVersion(string $resource, string $versionIdValue, array $changes): void
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        CatalogStorageMap::query($versions)->where($versionId, $versionIdValue)->update($changes);
    }

    public function publishedVersionExists(string $resource, string $reference): bool
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($versionId, $reference)->where('status', 'PUBLISHED')->exists();
    }

    public function publishedScheduleExists(string $hqId, string $reference): bool
    {
        return CatalogStorageMap::query('commitment_schedule_versions')->where('commitment_schedule_version_id', $reference)->where('hq_id', $hqId)->where('status', 'PUBLISHED')->exists();
    }

    public function history(string $resource, string $identityIdValue): array
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($identityId, $identityIdValue)->orderByDesc('version_number')->pluck($versionId)->all();
    }

    public function publishedOfferings(string $hqId): array
    {
        return CatalogStorageMap::query('service_offering_versions')->from('service_offering_versions as v')->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')->join('service_type_versions as stv', 'stv.service_type_version_id', '=', 'v.service_type_version_id')->join('shipping_method_versions as smv', 'smv.shipping_method_version_id', '=', 'v.shipping_method_version_id')->where('v.status', 'PUBLISHED')->where('i.status', 'ACTIVE')->where(fn($q) => $q->whereNull('v.hq_id')->orWhere('v.hq_id', $hqId))->select(['v.*', 'i.code as offering_code', 'stv.service_type_id', 'smv.shipping_method_id'])->orderBy('i.code')->limit(100)->get()->all();
    }

    public function latestPublishedOffering(string $hqId, string $offeringId): ?object
    {
        return CatalogStorageMap::query('service_offering_versions')->from('service_offering_versions as v')->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')->join('service_type_versions as stv', 'stv.service_type_version_id', '=', 'v.service_type_version_id')->join('shipping_method_versions as smv', 'smv.shipping_method_version_id', '=', 'v.shipping_method_version_id')->where('i.service_offering_id', $offeringId)->where('i.status', 'ACTIVE')->where('v.status', 'PUBLISHED')->where(fn($q) => $q->whereNull('v.hq_id')->orWhere('v.hq_id', $hqId))->orderByDesc('v.version_number')->select(['v.*', 'i.code as offering_code', 'stv.service_type_id', 'smv.shipping_method_id'])->first();
    }

    public function deleteOfferingChildren(string $versionId): void
    {
        foreach ([
            'service_offering_option_rules',
            'service_eligibility_rules',
            'service_coverage_references',
            'service_availability_bindings',
            'service_offering_commitment_bindings',
        ] as $table) {
            CatalogStorageMap::query($table)->where('service_offering_version_id', $versionId)->delete();
        }
    }

    public function insertOptionRules(array $attributes): void
    {
        CatalogStorageMap::query('service_offering_option_rules')->insert($attributes);
    }

    public function insertEligibilityRules(array $attributes): void
    {
        CatalogStorageMap::query('service_eligibility_rules')->insert($attributes);
    }

    public function insertCoverageReferences(array $attributes): void
    {
        CatalogStorageMap::query('service_coverage_references')->insert($attributes);
    }

    public function insertAvailabilityBindings(array $attributes): void
    {
        CatalogStorageMap::query('service_availability_bindings')->insert($attributes);
    }

    public function insertCommitmentBindings(array $attributes): void
    {
        CatalogStorageMap::query('service_offering_commitment_bindings')->insert($attributes);
    }

    public function activeProvince(string $value): bool
    {
        return DB::table('provinces')->where(['province_id' => $value, 'is_active' => true])->exists();
    }

    public function activeCity(string $value): bool
    {
        return DB::table('cities')->where(['city_id' => $value, 'is_active' => true])->exists();
    }

    public function tenantArea(string $hqId, string $value): bool
    {
        return DB::table('areas')->where(['area_id' => $value, 'hq_id' => $hqId])->exists();
    }

    public function visibleZoneSet(?string $hqId, string $value): bool
    {
        return DB::table('pricing_zone_set_versions as v')->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')->where('v.zone_set_version_id', $value)->where(fn($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $hqId))->exists();
    }

    public function optionRules(string $versionId): array
    {
        return CatalogStorageMap::query('service_offering_option_rules')->where('service_offering_version_id', $versionId)->get()->all();
    }

    public function eligibilityRules(string $versionId): array
    {
        return CatalogStorageMap::query('service_eligibility_rules')->where('service_offering_version_id', $versionId)->get()->all();
    }

    public function coverageReferences(string $versionId): array
    {
        return CatalogStorageMap::query('service_coverage_references')->where('service_offering_version_id', $versionId)->get()->all();
    }

    public function availabilityBindings(string $versionId): array
    {
        return CatalogStorageMap::query('service_availability_bindings')->where('service_offering_version_id', $versionId)->get()->all();
    }

    public function commitmentBindings(string $versionId): array
    {
        return CatalogStorageMap::query('service_offering_commitment_bindings')->where('service_offering_version_id', $versionId)->get()->all();
    }

    public function versionDetail(string $hqId, string $resource, string $versionIdValue): ?object
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->from("{$versions} as v")->join("{$identity} as i", "i.{$identityId}", '=', "v.{$identityId}")->where("v.{$versionId}", $versionIdValue)->where(fn($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $hqId))->select(['v.*', 'i.code'])->first();
    }

    public function commitmentBinding(string $versionIdValue): ?object
    {
        return CatalogStorageMap::query('service_offering_commitment_bindings')->where('service_offering_version_id', $versionIdValue)->first();
    }

    public function hasEffectiveOverlap(string $resource, array $row): bool
    {
        [, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        $query = CatalogStorageMap::query($versions)->where($identityId, $row[$identityId])->where($versionId, '!=', $row[$versionId])->whereIn('status', ['PUBLISHED', 'APPROVED']);
        if ($row['valid_to']) {
            $query->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<', $row['valid_to']));
        }
        if ($row['valid_from']) {
            $query->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $row['valid_from']));
        }
        return $query->exists();
    }

    public function optionIdentity(string $reference): ?string
    {
        return CatalogStorageMap::query('service_option_versions')->where('service_option_version_id', $reference)->value('service_option_id');
    }

    public function offeringOwner(string $offeringVersionId): ?string
    {
        return CatalogStorageMap::query('service_offering_versions')->where('service_offering_version_id', $offeringVersionId)->value('hq_id');
    }

    public function enabledAvailabilityBindings(string $versionId): array
    {
        return CatalogStorageMap::query('service_availability_bindings')->where('service_offering_version_id', $versionId)->where('enabled', true)->get()->all();
    }

    public function identityVisible(string $hqId, string $resource, string $value): bool
    {
        [$identity, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($identity)->where($identityId, $value)->where(fn($q) => $q->whereNull('hq_id')->orWhere('hq_id', $hqId))->exists();
    }
}
