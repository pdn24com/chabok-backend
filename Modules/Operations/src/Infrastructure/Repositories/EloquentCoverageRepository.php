<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Dto\CoveragePolicyFiltersDto;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;

final class EloquentCoverageRepository implements CoverageRepositoryInterface
{
    /** An open-ended period is compared against this upper bound. */
    private const OPEN_ENDED = '9999-12-31';

    public function policyExists(?string $hqId, string $policyId): bool
    {
        return CoveragePolicyRecord::query()->where(['hq_id' => $hqId, 'coverage_policy_id' => $policyId])->exists();
    }

    public function policyCodeTaken(?string $hqId, string $code): bool
    {
        return CoveragePolicyRecord::query()->where(['hq_id' => $hqId, 'policy_code' => $code])->exists();
    }

    public function findPolicy(?string $hqId, string $policyId): ?CoveragePolicyRecord
    {
        return CoveragePolicyRecord::query()->where(['hq_id' => $hqId, 'coverage_policy_id' => $policyId])->first();
    }

    public function paginatePolicies(?string $hqId, CoveragePolicyFiltersDto $filters): LengthAwarePaginator
    {
        $query = CoveragePolicyRecord::query()->where('hq_id', $hqId);
        if ($filters->search !== null) {
            $query->where(fn ($match) => $match->where('policy_code', 'like', '%'.$filters->search.'%')->orWhere('policy_title', 'like', '%'.$filters->search.'%'));
        }
        if ($filters->target !== null) {
            $query->whereHas('versions.rules', fn ($rules) => $rules->where('target', $filters->target));
        }

        return $query->orderBy('policy_code')->paginate((int) $filters->perPage, ['*'], 'page', (int) $filters->page);
    }

    public function updatePolicy(string $policyId, array $changes): void
    {
        CoveragePolicyRecord::query()->where('coverage_policy_id', $policyId)->update($changes);
    }

    public function clearPublishedVersion(string $policyId, string $versionId, array $changes): void
    {
        CoveragePolicyRecord::query()->where(['coverage_policy_id' => $policyId, 'published_version_id' => $versionId])->update($changes);
    }

    public function findVersionWithRules(?string $hqId, string $versionId): ?CoveragePolicyVersionRecord
    {
        return CoveragePolicyVersionRecord::query()->where(['hq_id' => $hqId, 'coverage_policy_version_id' => $versionId])
            ->with(['rules' => fn ($rules) => $rules->reorder('created_at')])->first();
    }

    public function findPolicyVersionWithRules(?string $hqId, string $policyId, string $versionId): ?CoveragePolicyVersionRecord
    {
        return $this->policyVersion($hqId, $policyId, $versionId)->first();
    }

    public function lockPolicyVersionWithRules(?string $hqId, string $policyId, string $versionId): ?CoveragePolicyVersionRecord
    {
        return $this->policyVersion($hqId, $policyId, $versionId)->lockForUpdate()->first();
    }

    public function paginateVersions(?string $hqId, string $policyId, int $page, int $perPage): LengthAwarePaginator
    {
        return CoveragePolicyVersionRecord::query()->where(['hq_id' => $hqId, 'coverage_policy_id' => $policyId])
            ->with('rules')->orderByDesc('version_number')->paginate($perPage, ['*'], 'page', $page);
    }

    public function nextVersionNumber(string $policyId): int
    {
        return (int) CoveragePolicyVersionRecord::query()->where('coverage_policy_id', $policyId)->max('version_number') + 1;
    }

    public function hasOverlappingPublishedVersion(?string $hqId, CoveragePolicyVersionRecord $version, DateTimeInterface $now): bool
    {
        return CoveragePolicyVersionRecord::query()->where(['hq_id' => $hqId, 'coverage_policy_id' => $version->coverage_policy_id, 'status' => ConfigVersionStatus::Published->value])
            ->where('coverage_policy_version_id', '!=', $version->coverage_policy_version_id)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $version->effective_from ?? $now))
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<', $version->effective_to ?? CarbonImmutable::parse(self::OPEN_ENDED)))
            ->exists();
    }

    public function deleteRules(string $versionId): void
    {
        CoverageRuleRecord::query()->where('coverage_policy_version_id', $versionId)->delete();
    }

    public function insertRules(array $rows): void
    {
        CoverageRuleRecord::query()->insert($rows);
    }

    public function effectiveRules(string $hqId, CoverageTarget $target, ?string $offeringVersionId, DateTimeInterface $at): Collection
    {
        return CoverageRuleRecord::query()->with('version.policy')
            ->where('hq_id', $hqId)->where('target', $target->value)
            ->whereHas('targetNode', fn ($query) => $query->where('status', 'ACTIVE'))
            ->whereHas('version', fn ($query) => $query->where('status', ConfigVersionStatus::Published->value)
                ->whereHas('policy', fn ($policy) => $policy->whereColumn('coverage_policies.published_version_id', 'coverage_policy_versions.id'))
                ->where(fn ($period) => $period->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
                ->where(fn ($period) => $period->whereNull('effective_to')->orWhere('effective_to', '>', $at)))
            ->where(fn ($query) => $query->whereNull('offering_version_id')
                ->when($offeringVersionId !== null, fn ($query) => $query->orWhere('offering_version_id', $offeringVersionId)))
            ->get();
    }

    /** @return Builder<CoveragePolicyVersionRecord> */
    private function policyVersion(?string $hqId, string $policyId, string $versionId): Builder
    {
        return CoveragePolicyVersionRecord::query()
            ->where(['hq_id' => $hqId, 'coverage_policy_id' => $policyId, 'coverage_policy_version_id' => $versionId])->with('rules');
    }
}
