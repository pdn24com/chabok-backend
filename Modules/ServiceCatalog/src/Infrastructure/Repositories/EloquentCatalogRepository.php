<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogIdentityRecordInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogVersionRecordInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final class EloquentCatalogRepository implements CatalogRepositoryInterface
{
    /** Relations an Offering detail response renders beyond its identity. */
    private const OFFERING_DETAIL_RELATIONS = ['optionRules', 'eligibilityRules', 'coverageReferences', 'availabilityBindings', 'commitmentBinding'];

    /** Schedule graph an Offering's commitment resolution walks. */
    private const COMMITMENT_RELATIONS = ['commitmentBinding.scheduleVersion.schedule.publishedVersion.windows',
        'commitmentBinding.scheduleVersion.schedule.publishedVersion.scopes'];

    /** Versions that already claim an effective interval for their identity. */
    public function identityExists(CatalogResource $resource, string $identityId, ?string $hqId): bool
    {
        return $this->identities($resource)->where($resource->identityKey(), $identityId)
            ->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))->exists();
    }

    public function codeTaken(CatalogResource $resource, string $ownerKey, string $code): bool
    {
        return $this->identities($resource)->where(['owner_key' => $ownerKey, 'code' => $code])->exists();
    }

    public function findTenantIdentity(CatalogResource $resource, string $identityId, ?string $hqId): ?CatalogIdentityRecordInterface
    {
        return $this->identities($resource)->where([$resource->identityKey() => $identityId, 'hq_id' => $hqId])->first();
    }

    public function lockTenantIdentity(CatalogResource $resource, string $identityId, ?string $hqId): ?CatalogIdentityRecordInterface
    {
        return $this->identities($resource)->where([$resource->identityKey() => $identityId, 'hq_id' => $hqId])->lockForUpdate()->first();
    }

    public function lockIdentity(CatalogResource $resource, string $identityId): ?CatalogIdentityRecordInterface
    {
        return $this->identities($resource)->where($resource->identityKey(), $identityId)->lockForUpdate()->first();
    }

    public function paginateIdentities(CatalogResource $resource, ?string $hqId, ?string $search, ?string $status, int $page, int $pageSize): LengthAwarePaginator
    {
        $query = $this->identities($resource)->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))->with('latestVersion');
        if ($search !== null) {
            $query->where('code', 'like', '%'.addcslashes($search, '%_\\').'%');
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('code')->paginate($pageSize, page: $page);
    }

    public function identitiesWithLatestVersion(CatalogResource $resource, array $identityIds): Collection
    {
        return $this->identities($resource)->whereIn($resource->identityKey(), $identityIds)
            ->with('latestVersion')->get()->keyBy($resource->identityKey());
    }

    public function lockVisibleIdentities(CatalogResource $resource, array $identityIds, string $hqId): Collection
    {
        $key = $resource->identityKey();

        // Lock stable identities in a consistent order before reading current revisions.
        return $this->identities($resource)->whereIn($key, $identityIds)
            ->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->orderBy($key)->lockForUpdate()->get();
    }

    public function createIdentity(CatalogResource $resource, array $attributes): string
    {
        return (string) $this->identities($resource)->forceCreate($attributes)->getKey();
    }

    public function bumpIdentityEditLock(CatalogResource $resource, string $identityId, array $extra): void
    {
        $this->identities($resource)->where($resource->identityKey(), $identityId)->increment('edit_lock', 1, $extra);
    }

    public function findTenantVersion(CatalogResource $resource, string $versionId, ?string $hqId): ?CatalogVersionRecordInterface
    {
        return $this->versions($resource)->where([$resource->versionKey() => $versionId, 'hq_id' => $hqId])->first();
    }

    public function lockTenantVersion(CatalogResource $resource, string $versionId, ?string $hqId): ?CatalogVersionRecordInterface
    {
        return $this->versions($resource)->where([$resource->versionKey() => $versionId, 'hq_id' => $hqId])->lockForUpdate()->first();
    }

    public function findVisibleVersionDetail(CatalogResource $resource, string $versionId, string $hqId): ?CatalogVersionRecordInterface
    {
        return $this->versionDetails($resource, $hqId)->where($resource->versionKey(), $versionId)->first();
    }

    public function versionHistoryOf(CatalogResource $resource, string $identityId, string $hqId): array
    {
        return $this->versionDetails($resource, $hqId)->where($resource->identityKey(), $identityId)
            ->orderByDesc('version_number')->get()->all();
    }

    public function latestVersionOf(CatalogResource $resource, string $identityId): ?CatalogVersionRecordInterface
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)->orderByDesc('version_number')->first();
    }

    public function lockLatestVersionOf(CatalogResource $resource, string $identityId): ?CatalogVersionRecordInterface
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)
            ->orderByDesc('version_number')->lockForUpdate()->first();
    }

    public function latestVersionIdOf(CatalogResource $resource, string $identityId): ?string
    {
        $id = $this->versions($resource)->where($resource->identityKey(), $identityId)
            ->orderByDesc('version_number')->value($resource->versionKey());

        return $id === null ? null : (string) $id;
    }

    public function identityIdOfVersion(CatalogResource $resource, string $versionId): ?string
    {
        $id = $this->versions($resource)->where($resource->versionKey(), $versionId)->value($resource->identityKey());

        return $id === null ? null : (string) $id;
    }

    public function identityIdsOfVersions(CatalogResource $resource, array $versionIds): array
    {
        return $this->versions($resource)->whereIn($resource->versionKey(), $versionIds)
            ->pluck($resource->identityKey(), $resource->versionKey())->all();
    }

    public function versionIdsOfIdentity(CatalogResource $resource, string $identityId): array
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)->pluck($resource->versionKey())->all();
    }

    public function versionPublished(CatalogResource $resource, string $versionId): bool
    {
        return $this->versions($resource)->where([$resource->versionKey() => $versionId, 'status' => VersionLifecycleStatus::Published->value])->exists();
    }

    public function hasVersionWithStatus(CatalogResource $resource, string $identityId, array $statuses): bool
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)->whereIn('status', $statuses)->exists();
    }

    public function publishedVersionIdFor(CatalogResource $resource, string $identityId, ?string $hqId): ?string
    {
        $id = $this->versions($resource)->where($resource->identityKey(), $identityId)->where('status', VersionLifecycleStatus::Published->value)
            ->whereHas($this->identityRelation($resource), fn ($identity) => $identity->where('status', 'ACTIVE')
                ->when($hqId !== null, fn ($q) => $q->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))))
            ->orderByDesc('version_number')->value($resource->versionKey());

        return $id === null ? null : (string) $id;
    }

    public function lockPublishedVersionsByIdentity(CatalogResource $resource, array $identityIds): Collection
    {
        $key = $resource->identityKey();

        return $this->versions($resource)->whereIn($key, $identityIds)->where('status', VersionLifecycleStatus::Published->value)
            ->orderBy($key)->orderByDesc('version_number')->lockForUpdate()->get()->groupBy($key);
    }

    public function createVersion(CatalogResource $resource, array $attributes): string
    {
        return (string) $this->versions($resource)->forceCreate($attributes)->getKey();
    }

    public function replicateAsDraft(CatalogVersionRecordInterface $previous, array $overrides): string
    {
        $copy = $previous->replicate(['approved_by', 'approved_at', 'published_by', 'published_at', 'content_digest'])
            ->forceFill($overrides);
        $copy->save();

        return (string) $copy->getKey();
    }

    public function applyVersion(CatalogVersionRecordInterface $version, array $changes): void
    {
        $version->forceFill($changes)->save();
    }

    public function updateVersion(CatalogResource $resource, string $versionId, array $changes): void
    {
        $this->versions($resource)->where($resource->versionKey(), $versionId)->update($changes);
    }

    public function supersedePublishedVersions(CatalogResource $resource, string $identityId, array $changes): void
    {
        $this->versions($resource)->where($resource->identityKey(), $identityId)->where('status', VersionLifecycleStatus::Published->value)->update($changes);
    }

    public function hasOverlappingEffectiveVersion(CatalogResource $resource, string $identityId, string $versionId, mixed $validFrom, mixed $validTo): bool
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)
            ->where($resource->versionKey(), '!=', $versionId)->whereIn('status', VersionLifecycleStatus::valuesOf(VersionLifecycleStatus::effective()))
            ->when($validTo, fn ($query) => $query->where(fn ($interval) => $interval->whereNull('valid_from')->orWhere('valid_from', '<', $validTo)))
            ->when($validFrom, fn ($query) => $query->where(fn ($interval) => $interval->whereNull('valid_to')->orWhere('valid_to', '>', $validFrom)))
            ->exists();
    }

    public function publishedVersionIdsAmong(CatalogResource $resource, array $versionIds): array
    {
        return $this->versions($resource)->whereIn($resource->versionKey(), $versionIds)
            ->where('status', VersionLifecycleStatus::Published->value)->pluck($resource->versionKey())->all();
    }

    public function visibleVersionIdsAmong(CatalogResource $resource, array $versionIds, ?string $hqId): array
    {
        return $this->versions($resource)->whereIn($resource->versionKey(), array_unique($versionIds))
            ->whereHas($this->identityRelation($resource), fn ($identity) => $identity->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId)))
            ->pluck($resource->versionKey())->all();
    }

    public function paginateAvailableVersions(CatalogResource $resource, string $hqId, array $includedVersionIds, ?string $search, int $page, int $pageSize): LengthAwarePaginator
    {
        $relation = $this->identityRelation($resource);
        $versionKey = $resource->versionKey();
        $identityKey = $resource->identityKey();
        $query = $this->visibleVersions($resource, $hqId);
        $query->where(function ($available) use ($includedVersionIds, $search, $versionKey, $relation): void {
            $available->where(function ($published) use ($search, $relation): void {
                $published->where('status', VersionLifecycleStatus::Published->value)->whereHas($relation, fn ($identity) => $identity->where('status', 'ACTIVE'));
                if ($search !== null) {
                    $published->where(fn ($match) => $match->where('labels', 'like', $search)->orWhereHas($relation, fn ($identity) => $identity->where('code', 'like', $search)));
                }
            });
            if ($includedVersionIds !== []) {
                $available->orWhereIn($versionKey, $includedVersionIds);
            }
        });
        // A correlated code expression preserves database pagination/order across the native identity relation.
        $codeOrder = $this->identities($resource)->select('code')->whereColumn('id', $query->getModel()->getTable().'.'.$identityKey);

        return $query->orderBy($codeOrder)->orderByDesc('version_number')->paginate($pageSize, page: $page);
    }

    public function publishedOfferings(string $hqId, int $limit): Collection
    {
        return $this->publishedOfferingQuery($hqId)
            ->orderBy(ServiceOfferingRecord::query()->select('code')->whereColumn('id', 'service_offering_versions.service_offering_id'))
            ->limit($limit)->get();
    }

    public function newestPublishedOffering(string $hqId, string $offeringId): ?CatalogVersionRecordInterface
    {
        return $this->publishedOfferingQuery($hqId)->where('service_offering_id', $offeringId)->orderByDesc('version_number')->first();
    }

    public function findOfferingVersionWithCommitment(string $offeringVersionId): ?CatalogVersionRecordInterface
    {
        return ServiceOfferingVersionRecord::query()->where('service_offering_version_id', $offeringVersionId)
            ->with(self::COMMITMENT_RELATIONS)->first();
    }

    public function optionsWithVersionsByReference(array $references): Collection
    {
        return ServiceOptionRecord::query()->where(fn ($query) => $query
            ->whereIn('service_option_id', $references)
            ->orWhereIn('id', ServiceOptionVersionRecord::query()->select('service_option_id')->whereIn('service_option_version_id', $references)))
            ->with('versions')->get();
    }

    public function activeOfferingsWithPublishedVersion(array $offeringIds): Collection
    {
        return ServiceOfferingRecord::query()->whereIn('service_offering_id', $offeringIds)->where('status', 'ACTIVE')
            ->with(['publishedVersions' => fn ($versions) => $versions->limit(1)->with('optionRules.optionVersion')])
            ->get()->keyBy('service_offering_id');
    }

    public function activeOptionsWithPublishedVersion(array $optionIds, string $hqId): Collection
    {
        return ServiceOptionRecord::query()->whereIn('service_option_id', $optionIds)->where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->with(['publishedVersions' => fn ($versions) => $versions->limit(1)])->get()->keyBy('service_option_id');
    }

    public function publishedVersionsOfVisibleIdentities(CatalogResource $resource, array $identityIds, string $hqId): Collection
    {
        $key = $resource->identityKey();

        return $this->versions($resource)->whereIn($key, $identityIds)->where('status', VersionLifecycleStatus::Published->value)
            ->whereHas($this->identityRelation($resource), fn ($identity) => $identity->where('status', 'ACTIVE')
                ->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId)))
            ->orderByDesc('version_number')->get()->groupBy($key);
    }

    private function publishedOfferingQuery(string $hqId): Builder
    {
        return ServiceOfferingVersionRecord::query()->where('status', VersionLifecycleStatus::Published->value)
            ->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))
            ->whereHas('offering', fn ($identity) => $identity->where('status', 'ACTIVE'))
            ->whereHas('serviceTypeVersion')->whereHas('shippingMethodVersion')
            ->with(self::COMMITMENT_RELATIONS)
            ->with(['offering', 'serviceTypeVersion', 'shippingMethodVersion', 'availabilityBindings', 'coverageReferences', 'eligibilityRules', 'optionRules.optionVersion.option.publishedVersions' => fn ($versions) => $versions->limit(1)]);
    }

    private function identities(CatalogResource $resource): Builder
    {
        return match ($resource) {
            CatalogResource::ServiceType => ServiceTypeRecord::query(),
            CatalogResource::ShippingMethod => ShippingMethodRecord::query(),
            CatalogResource::Offering => ServiceOfferingRecord::query(),
            CatalogResource::Option => ServiceOptionRecord::query(),
            CatalogResource::CommitmentSchedule => CommitmentScheduleRecord::query(),
        };
    }

    private function versions(CatalogResource $resource): Builder
    {
        return match ($resource) {
            CatalogResource::ServiceType => ServiceTypeVersionRecord::query(),
            CatalogResource::ShippingMethod => ShippingMethodVersionRecord::query(),
            CatalogResource::Offering => ServiceOfferingVersionRecord::query(),
            CatalogResource::Option => ServiceOptionVersionRecord::query(),
            CatalogResource::CommitmentSchedule => CommitmentScheduleVersionRecord::query(),
        };
    }

    private function identityRelation(CatalogResource $resource): string
    {
        return match ($resource) {
            CatalogResource::ServiceType => 'type',
            CatalogResource::ShippingMethod => 'method',
            CatalogResource::Offering => 'offering',
            CatalogResource::Option => 'option',
            CatalogResource::CommitmentSchedule => 'schedule',
        };
    }

    private function visibleVersions(CatalogResource $resource, string $hqId): Builder
    {
        $relation = $this->identityRelation($resource);

        return $this->versions($resource)->whereHas($relation, fn ($identity) => $identity
            ->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId)))->with($relation);
    }

    private function versionDetails(CatalogResource $resource, string $hqId): Builder
    {
        $query = $this->visibleVersions($resource, $hqId);
        if ($resource === CatalogResource::Offering) {
            $query->with(self::OFFERING_DETAIL_RELATIONS);
        }

        return $query;
    }
}
