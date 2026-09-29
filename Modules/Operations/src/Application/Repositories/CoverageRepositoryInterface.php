<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Dto\CoveragePolicyFiltersDto;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

interface CoverageRepositoryInterface
{
    public function policyExists(?string $hqId, string $policyId): bool;

    public function policyCodeTaken(?string $hqId, string $code): bool;

    public function findPolicy(?string $hqId, string $policyId): ?CoveragePolicyRecord;

    /** @return LengthAwarePaginator<CoveragePolicyRecord> */
    public function paginatePolicies(?string $hqId, CoveragePolicyFiltersDto $filters): LengthAwarePaginator;

    /** @param array<string, mixed> $changes */
    public function updatePolicy(string $policyId, array $changes): void;

    /** Clears the published pointer only while it still names this version. @param array<string, mixed> $changes */
    public function clearPublishedVersion(string $policyId, string $versionId, array $changes): void;

    public function findVersionWithRules(?string $hqId, string $versionId): ?CoveragePolicyVersionRecord;

    public function findPolicyVersionWithRules(?string $hqId, string $policyId, string $versionId): ?CoveragePolicyVersionRecord;

    public function lockPolicyVersionWithRules(?string $hqId, string $policyId, string $versionId): ?CoveragePolicyVersionRecord;

    /** @return LengthAwarePaginator<CoveragePolicyVersionRecord> */
    public function paginateVersions(?string $hqId, string $policyId, int $page, int $perPage): LengthAwarePaginator;

    public function nextVersionNumber(string $policyId): int;

    /** Whether another published version of the same policy already covers an overlapping period. */
    public function hasOverlappingPublishedVersion(?string $hqId, CoveragePolicyVersionRecord $version, DateTimeInterface $now): bool;

    public function deleteRules(string $versionId): void;

    /** @param list<array<string, mixed>> $rows */
    public function insertRules(array $rows): void;

    /** Published rules for a target whose policy still points at their version, effective at a moment. @return Collection<int, \Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord> */
    public function effectiveRules(string $hqId, CoverageTarget $target, ?string $offeringVersionId, DateTimeInterface $at): Collection;
}
