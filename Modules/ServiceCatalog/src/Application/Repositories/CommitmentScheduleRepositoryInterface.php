<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

interface CommitmentScheduleRepositoryInterface
{
    public function identityExists(?string $hqId, string $identityId): bool;

    public function lockIdentity(?string $hqId, string $identityId): ?CommitmentScheduleRecord;

    /** @param array<string, mixed> $changes */
    public function updateIdentity(?string $hqId, string $identityId, array $changes): void;

    /** @return LengthAwarePaginator<CommitmentScheduleRecord> */
    public function paginateIdentities(?string $hqId, string $search, int $page, int $pageSize): LengthAwarePaginator;

    /** @param list<string> $identityIds @return Collection<string, CommitmentScheduleRecord> */
    public function identitiesWithLatestVersion(?string $hqId, array $identityIds): Collection;

    public function lockTenantVersion(?string $hqId, string $versionId): ?CommitmentScheduleVersionRecord;

    /** @param array<string, mixed> $changes */
    public function applyVersion(CommitmentScheduleVersionRecord $version, array $changes): void;

    public function publishedVersionExists(?string $hqId, string $versionId): bool;

    /** Published versions whose scope covers a Node, with their windows, for a pickup-window listing. @return Collection<int, CommitmentScheduleVersionRecord> */
    public function publishedVersionsScopedToNode(?string $hqId, string $nodeId): Collection;

    /** Whether another approved or published version of the same schedule covers an overlapping interval. */
    public function hasOverlappingEffectiveVersion(string $identityId, string $versionId, mixed $validFrom, mixed $validTo): bool;

    /** Versions of one tenant, with the graph a schedule response renders. @return Collection<int, CommitmentScheduleVersionRecord> */
    public function tenantVersions(string $hqId): Collection;

    public function findTenantVersion(string $hqId, string $versionId): ?CommitmentScheduleVersionRecord;

    /** One schedule's versions, newest first. @return Collection<int, CommitmentScheduleVersionRecord> */
    public function versionHistoryOf(string $hqId, string $identityId): Collection;

    /** Versions available to a tenant: published under an active schedule, plus any explicitly included ids. @param list<string> $includedVersionIds @return Collection<int, CommitmentScheduleVersionRecord> */
    public function availableVersions(string $hqId, array $includedVersionIds): Collection;

    public function hasUnpublishedSuccessor(CommitmentScheduleRecord $identity): bool;

    /** @return CommitmentScheduleVersionRecord|null The newest version with its children, locked for cloning. */
    public function lockLatestVersionWithChildren(CommitmentScheduleRecord $identity): ?CommitmentScheduleVersionRecord;

    /** Copies a version and its windows and scopes onto a fresh draft. @param array<string, mixed> $overrides */
    public function replicateAsDraft(CommitmentScheduleVersionRecord $previous, array $overrides): string;
}
