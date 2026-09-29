<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogIdentityRecordInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Contracts\CatalogVersionRecordInterface;

/**
 * The five catalogue resources share one lifecycle: a stable identity owns an ordered chain of versions.
 * Every method therefore takes the resource it acts on instead of each caller choosing a model.
 */
interface CatalogRepositoryInterface
{
    public function identityExists(CatalogResource $resource, string $identityId, ?string $hqId): bool;

    public function codeTaken(CatalogResource $resource, string $ownerKey, string $code): bool;

    public function findTenantIdentity(CatalogResource $resource, string $identityId, ?string $hqId): ?CatalogIdentityRecordInterface;

    public function lockTenantIdentity(CatalogResource $resource, string $identityId, ?string $hqId): ?CatalogIdentityRecordInterface;

    public function lockIdentity(CatalogResource $resource, string $identityId): ?CatalogIdentityRecordInterface;

    /** @return LengthAwarePaginator<CatalogVersionRecordInterface> */
    public function paginateIdentities(CatalogResource $resource, ?string $hqId, ?string $search, ?string $status, int $page, int $pageSize): LengthAwarePaginator;

    /** @param list<string> $identityIds @return Collection<string, CatalogIdentityRecordInterface> */
    public function identitiesWithLatestVersion(CatalogResource $resource, array $identityIds): Collection;

    /** Locks the named identities in key order before their current versions are read. @param list<string> $identityIds @return Collection<int, CatalogVersionRecordInterface> */
    public function lockVisibleIdentities(CatalogResource $resource, array $identityIds, string $hqId): Collection;

    /** @param array<string, mixed> $attributes */
    public function createIdentity(CatalogResource $resource, array $attributes): string;

    /** @param array<string, mixed> $extra */
    public function bumpIdentityEditLock(CatalogResource $resource, string $identityId, array $extra): void;

    public function findTenantVersion(CatalogResource $resource, string $versionId, ?string $hqId): ?CatalogVersionRecordInterface;

    public function lockTenantVersion(CatalogResource $resource, string $versionId, ?string $hqId): ?CatalogVersionRecordInterface;

    /** The version a detail response renders, with the relations that response needs. */
    public function findVisibleVersionDetail(CatalogResource $resource, string $versionId, string $hqId): ?CatalogVersionRecordInterface;

    public function latestVersionOf(CatalogResource $resource, string $identityId): ?CatalogVersionRecordInterface;

    /** Replicates the newest version as a fresh draft, which is how an edit starts from the published content. @param array<string, mixed> $overrides */
    public function replicateAsDraft(CatalogVersionRecordInterface $previous, array $overrides): string;

    public function lockLatestVersionOf(CatalogResource $resource, string $identityId): ?CatalogVersionRecordInterface;

    public function latestVersionIdOf(CatalogResource $resource, string $identityId): ?string;

    public function identityIdOfVersion(CatalogResource $resource, string $versionId): ?string;

    /** @param list<string> $versionIds @return array<string, string> */
    public function identityIdsOfVersions(CatalogResource $resource, array $versionIds): array;

    /** @return list<string> */
    public function versionIdsOfIdentity(CatalogResource $resource, string $identityId): array;

    public function versionPublished(CatalogResource $resource, string $versionId): bool;

    /** @param list<string> $statuses */
    public function hasVersionWithStatus(CatalogResource $resource, string $identityId, array $statuses): bool;

    /** The newest published version id of an identity that is still active and visible to the tenant. */
    public function publishedVersionIdFor(CatalogResource $resource, string $identityId, ?string $hqId): ?string;

    /** Published versions of the given active identities, newest first, locked in key order. @param list<string> $identityIds @return Collection<string, Collection<int, CatalogVersionRecordInterface>> */
    public function lockPublishedVersionsByIdentity(CatalogResource $resource, array $identityIds): Collection;

    /** @param array<string, mixed> $attributes */
    public function createVersion(CatalogResource $resource, array $attributes): string;

    /** @param array<string, mixed> $changes */
    public function applyVersion(CatalogVersionRecordInterface $version, array $changes): void;

    /** @param array<string, mixed> $changes */
    public function updateVersion(CatalogResource $resource, string $versionId, array $changes): void;

    /** @param array<string, mixed> $changes */
    public function supersedePublishedVersions(CatalogResource $resource, string $identityId, array $changes): void;

    /**
     * Whether another approved or published version of the same identity covers an overlapping interval,
     * which is what stops two versions from being effective at once.
     */
    public function hasOverlappingEffectiveVersion(CatalogResource $resource, string $identityId, string $versionId, mixed $validFrom, mixed $validTo): bool;

    /** @param list<string> $versionIds @return list<string> */
    public function publishedVersionIdsAmong(CatalogResource $resource, array $versionIds): array;

    /** Version ids among the candidates whose identity is visible to the tenant. @param list<string> $versionIds @return list<string> */
    public function visibleVersionIdsAmong(CatalogResource $resource, array $versionIds, ?string $hqId): array;

    /** Versions available to a tenant: published under an active identity, plus any explicitly included ids. @param list<string> $includedVersionIds @return LengthAwarePaginator<CatalogVersionRecordInterface> */
    public function paginateAvailableVersions(CatalogResource $resource, string $hqId, array $includedVersionIds, ?string $search, int $page, int $pageSize): LengthAwarePaginator;

    /** @return list<CatalogVersionRecordInterface> */
    public function versionHistoryOf(CatalogResource $resource, string $identityId, string $hqId): array;

    /** Published Offering versions for a tenant, ordered by Offering code. @return Collection<int, CatalogVersionRecordInterface> */
    public function publishedOfferings(string $hqId, int $limit): Collection;

    public function newestPublishedOffering(string $hqId, string $offeringId): ?CatalogVersionRecordInterface;

    public function findOfferingVersionWithCommitment(string $offeringVersionId): ?CatalogVersionRecordInterface;

    /**
     * Options addressable by either their identity or any of their version ids, with the whole version
     * chain loaded, so a legacy reference resolves without one query per reference.
     *
     * @param  list<string>  $references
     * @return Collection<int, CatalogVersionRecordInterface>
     */
    public function optionsWithVersionsByReference(array $references): Collection;
}
