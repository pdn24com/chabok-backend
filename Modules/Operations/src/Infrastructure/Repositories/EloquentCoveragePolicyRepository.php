<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Data\Page;
use Modules\Operations\Application\Repositories\CoveragePolicyRepository;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;

final class EloquentCoveragePolicyRepository implements CoveragePolicyRepository
{
    public function policies(string $hq, array $filters): Page
    {
        $query = CoveragePolicyRecord::query()->toBase()->where('hq_id', $hq);
        if (($filters['search'] ?? null) !== null) {
            $query->where(fn($q) => $q->where('policy_code', 'like', '%' . $filters['search'] . '%')->orWhere('policy_title', 'like', '%' . $filters['search'] . '%'));
        }
        if (($filters['target'] ?? null) !== null) {
            $query->whereExists(fn($q) => $q->selectRaw('1')->from('coverage_policy_versions as v')->join('coverage_rules as r', 'r.coverage_policy_version_id', '=', 'v.coverage_policy_version_id')->whereColumn('v.coverage_policy_id', 'coverage_policies.coverage_policy_id')->where('r.target', $filters['target']));
        }
        $page = $query->orderBy('policy_code')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function codeExists(string $hq, string $code): bool
    {
        return CoveragePolicyRecord::query()->toBase()->where(['hq_id' => $hq, 'policy_code' => $code])->exists();
    }

    public function policy(string $hq, string $id): ?object
    {
        return CoveragePolicyRecord::query()->toBase()->where(['hq_id' => $hq, 'coverage_policy_id' => $id])->first();
    }

    public function policyExists(string $hq, string $policyId): bool
    {
        return CoveragePolicyRecord::query()->toBase()->where(['hq_id' => $hq, 'coverage_policy_id' => $policyId])->exists();
    }

    public function history(string $hq, string $policyId, int $page, int $perPage): Page
    {
        $result = CoveragePolicyVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'coverage_policy_id' => $policyId])->orderByDesc('version_number')->paginate($perPage, ['*'], 'page', $page);
        return new Page($result->items(), $result->currentPage(), $result->perPage(), $result->total());
    }

    public function lastVersionNumber(string $policyId): int
    {
        return (int) CoveragePolicyVersionRecord::query()->toBase()->where('coverage_policy_id', $policyId)->max('version_number');
    }

    public function insertPolicy(array $attributes): void
    {
        CoveragePolicyRecord::query()->toBase()->insert($attributes);
    }

    public function insertVersion(array $attributes): void
    {
        CoveragePolicyVersionRecord::query()->toBase()->insert($attributes);
    }

    public function insertRule(array $attributes): void
    {
        CoverageRuleRecord::query()->toBase()->insert($attributes);
    }

    public function deleteRules(string $versionId): void
    {
        CoverageRuleRecord::query()->toBase()->where('coverage_policy_version_id', $versionId)->delete();
    }

    public function updateVersion(string $versionId, array $changes): void
    {
        CoveragePolicyVersionRecord::query()->toBase()->where('coverage_policy_version_id', $versionId)->update($changes);
    }

    public function publishPolicy(string $policyId, string $versionId, \DateTimeInterface $at): void
    {
        CoveragePolicyRecord::query()->toBase()->where('coverage_policy_id', $policyId)->update(['published_version_id' => $versionId, 'updated_at' => $at]);
    }

    public function supersedePolicy(string $policyId, string $versionId, \DateTimeInterface $at): void
    {
        CoveragePolicyRecord::query()->toBase()->where(['coverage_policy_id' => $policyId, 'published_version_id' => $versionId])->update(['published_version_id' => null, 'updated_at' => $at]);
    }

    public function matchingRules(string $hqId, string $target, ?string $offeringVersionId, \DateTimeInterface $at): array
    {
        return CoverageRuleRecord::query()->toBase()->from('coverage_rules as r')->join('coverage_policy_versions as v', 'v.coverage_policy_version_id', '=', 'r.coverage_policy_version_id')->join('coverage_policies as p', 'p.coverage_policy_id', '=', 'v.coverage_policy_id')->join('nodes as n', 'n.node_id', '=', 'r.target_node_id')->where(['r.hq_id' => $hqId, 'r.target' => $target, 'v.status' => 'PUBLISHED', 'n.status' => 'ACTIVE'])->whereColumn('p.published_version_id', 'v.coverage_policy_version_id')->where(fn($q) => $q->whereNull('v.effective_from')->orWhere('v.effective_from', '<=', $at))->where(fn($q) => $q->whereNull('v.effective_to')->orWhere('v.effective_to', '>', $at))->where(fn($q) => $q->whereNull('r.offering_version_id')->when($offeringVersionId !== null, fn($inner) => $inner->orWhere('r.offering_version_id', $offeringVersionId)))->get([
            'r.*',
            'v.coverage_policy_id',
            'v.coverage_policy_version_id',
            'v.version_number',
            'p.policy_code',
            'p.policy_title',
        ])->all();
    }

    public function offeringVisible(string $hq, string $offeringVersionId): bool
    {
        return DB::table('service_offering_versions as v')->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')->where('v.service_offering_version_id', $offeringVersionId)->where(fn($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $hq))->exists();
    }

    public function activeNode(string $hq, string $nodeId): bool
    {
        return DB::table('nodes')->where(['hq_id' => $hq, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->exists();
    }

    public function versionExists(string $hq, string $versionId): bool
    {
        return CoveragePolicyVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'coverage_policy_version_id' => $versionId])->exists();
    }

    public function version(string $hq, string $policy, string $version): ?object
    {
        return CoveragePolicyVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'coverage_policy_id' => $policy, 'coverage_policy_version_id' => $version])->first();
    }

    public function lockVersion(string $hq, string $policy, string $version): ?object
    {
        return CoveragePolicyVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'coverage_policy_id' => $policy, 'coverage_policy_version_id' => $version])->lockForUpdate()->first();
    }

    public function provinceExists(string $id): bool
    {
        return DB::table('provinces')->where('province_id', $id)->exists();
    }

    public function cityExists(string $id): bool
    {
        return DB::table('cities')->where('city_id', $id)->exists();
    }

    public function rulesInCreationOrder(string $versionId): array
    {
        return CoverageRuleRecord::query()->toBase()->where('coverage_policy_version_id', $versionId)->orderBy('created_at')->get()->all();
    }

    public function rulesByPriority(string $versionId): array
    {
        return CoverageRuleRecord::query()->toBase()->where('coverage_policy_version_id', $versionId)->orderByDesc('priority')->get()->all();
    }

    public function publishedDatesOverlap(string $hq, string $policyId, string $versionId, mixed $effectiveFrom, mixed $effectiveTo): bool
    {
        return CoveragePolicyVersionRecord::query()->toBase()->where(['hq_id' => $hq, 'coverage_policy_id' => $policyId, 'status' => 'PUBLISHED'])->where('coverage_policy_version_id', '!=', $versionId)->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $effectiveFrom))->where(fn($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<', $effectiveTo))->exists();
    }
}
