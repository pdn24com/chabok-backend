<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Geography\Domain\GeoJsonGeometry;

final readonly class CoveragePolicyService
{
    private const SPECIFICITY = ['PROVINCE' => 1, 'CITY' => 2, 'POSTAL_RANGE' => 3, 'POLYGON' => 4, 'POINT_RADIUS' => 4];

    public function __construct(
        private NetworkAccessGuard $access,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string,mixed> $filters */
    public function list(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        $query = DB::table('coverage_policies')->where('hq_id', $hq);
        if (($filters['search'] ?? null) !== null) $query->where(fn ($q) => $q->where('policy_code', 'like', '%'.$filters['search'].'%')->orWhere('policy_title', 'like', '%'.$filters['search'].'%'));
        if (($filters['target'] ?? null) !== null) $query->whereExists(fn ($q) => $q->selectRaw('1')->from('coverage_policy_versions as v')->join('coverage_rules as r', 'r.coverage_policy_version_id', '=', 'v.coverage_policy_version_id')->whereColumn('v.coverage_policy_id', 'coverage_policies.coverage_policy_id')->where('r.target', $filters['target']));
        return $query->orderBy('policy_code')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $input, $correlationId): string {
            if (DB::table('coverage_policies')->where(['hq_id' => $hq, 'policy_code' => $input['policy_code']])->exists()) throw new ApiException(ApiErrorCode::Conflict, 409, 'The Coverage Policy code already exists.');
            $id = (string) Str::uuid();
            DB::table('coverage_policies')->insert(['coverage_policy_id' => $id, 'hq_id' => $hq, 'policy_code' => $input['policy_code'], 'policy_title' => $input['policy_title'], 'created_at' => now(), 'updated_at' => now()]);
            $this->record($actor, 'COVERAGE_POLICY_CREATED', 'COVERAGE_POLICY', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->policy($actor, $id);
    }

    /** @return array<string,mixed> */
    public function policy(AuthenticatedPrincipal $actor, string $id): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        $row = DB::table('coverage_policies')->where(['hq_id' => $hq, 'coverage_policy_id' => $id])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $this->policyArray($row);
    }

    public function history(AuthenticatedPrincipal $actor, string $policyId, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        if (! DB::table('coverage_policies')->where(['hq_id' => $hq, 'coverage_policy_id' => $policyId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return DB::table('coverage_policy_versions')->where(['hq_id' => $hq, 'coverage_policy_id' => $policyId])->orderByDesc('version_number')->paginate($perPage, ['*'], 'page', $page);
    }

    /** @return array<string,mixed> */
    public function presentVersion(object $row): array { return $this->versionArray($row); }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createVersion(AuthenticatedPrincipal $actor, string $policyId, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $policyId, $input, $correlationId): string {
            if (! DB::table('coverage_policies')->where(['hq_id' => $hq, 'coverage_policy_id' => $policyId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $rules = (array) ($input['rules'] ?? []);
            if (($input['source_version_id'] ?? null) !== null && $rules === []) $rules = $this->ruleInputs($hq, (string) $input['source_version_id']);
            $this->validateRuleInputs($hq, $rules);
            $id = (string) Str::uuid();
            $number = ((int) DB::table('coverage_policy_versions')->where('coverage_policy_id', $policyId)->max('version_number')) + 1;
            DB::table('coverage_policy_versions')->insert(['coverage_policy_version_id' => $id, 'hq_id' => $hq, 'coverage_policy_id' => $policyId, 'version_number' => $number, 'status' => 'DRAFT', 'effective_from' => $input['effective_from'] ?? null, 'effective_to' => $input['effective_to'] ?? null, 'version' => 1, 'created_by' => $actor->userId, 'created_at' => now(), 'updated_at' => now()]);
            $this->replaceRules($hq, $id, $rules);
            $this->record($actor, 'COVERAGE_VERSION_CREATED', 'COVERAGE_POLICY_VERSION', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->version($actor, $policyId, $id);
    }

    /** @return array<string,mixed> */
    public function version(AuthenticatedPrincipal $actor, string $policyId, string $versionId): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        $row = $this->versionRow($hq, $policyId, $versionId);
        return $this->versionArray($row);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(AuthenticatedPrincipal $actor, string $policyId, string $versionId, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.manage_draft');
        $this->transactions->run(function () use ($actor, $hq, $policyId, $versionId, $input, $correlationId): void {
            $row = $this->lockedVersion($hq, $policyId, $versionId);
            if ($row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft version is editable.');
            $this->expected($row, (int) $input['expected_version']);
            $changes = ['version' => (int) $row->version + 1, 'updated_at' => now()];
            foreach (['effective_from', 'effective_to'] as $field) if (array_key_exists($field, $input)) $changes[$field] = $input[$field];
            if (array_key_exists('rules', $input)) { $this->validateRuleInputs($hq, $input['rules']); $this->replaceRules($hq, $versionId, $input['rules']); }
            DB::table('coverage_policy_versions')->where('coverage_policy_version_id', $versionId)->update($changes);
            $this->record($actor, 'COVERAGE_VERSION_UPDATED', 'COVERAGE_POLICY_VERSION', $versionId, 'DRAFT', $correlationId);
        });
        return $this->version($actor, $policyId, $versionId);
    }

    /** @return array<string,mixed> */
    public function transition(AuthenticatedPrincipal $actor, string $policyId, string $versionId, string $action, int $expected, ?string $note, string $correlationId): array
    {
        $permission = match ($action) { 'validate' => 'network.coverage.validate', 'approve' => 'network.coverage.approve', 'publish', 'supersede' => 'network.coverage.publish', default => 'network.coverage.manage_draft' };
        $hq = $this->access->assert($actor, $permission);
        $this->transactions->run(function () use ($actor, $hq, $policyId, $versionId, $action, $expected, $note, $correlationId): void {
            $row = $this->lockedVersion($hq, $policyId, $versionId); $this->expected($row, $expected);
            $next = match ($action) {
                'validate' => $this->validatedChanges($hq, $row, $actor->userId),
                'approve' => $this->simpleChanges($row, 'VALIDATED', 'APPROVED', ['approved_by' => $actor->userId, 'approved_at' => now()]),
                'publish' => $this->publishedChanges($hq, $row, $actor->userId),
                'supersede' => $this->simpleChanges($row, 'PUBLISHED', 'SUPERSEDED'),
                'archive' => $this->archiveChanges($row),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unknown lifecycle action.'),
            };
            $next['version'] = (int) $row->version + 1; $next['updated_at'] = now();
            DB::table('coverage_policy_versions')->where('coverage_policy_version_id', $versionId)->update($next);
            if ($action === 'publish') DB::table('coverage_policies')->where('coverage_policy_id', $policyId)->update(['published_version_id' => $versionId, 'updated_at' => now()]);
            if ($action === 'supersede') DB::table('coverage_policies')->where(['coverage_policy_id' => $policyId, 'published_version_id' => $versionId])->update(['published_version_id' => null, 'updated_at' => now()]);
            $this->record($actor, 'COVERAGE_VERSION_'.strtoupper($action), 'COVERAGE_POLICY_VERSION', $versionId, (string) $next['status'], $correlationId, $note);
        });
        return $this->version($actor, $policyId, $versionId);
    }

    /** @param array{province_id?:string,city_id?:string,postal_code?:string,latitude?:float,longitude?:float} $input @return array<string,mixed> */
    public function resolve(string $hqId, string $target, array $input, ?string $offeringVersionId = null, ?Carbon $at = null): array
    {
        $at ??= now();
        $rows = DB::table('coverage_rules as r')->join('coverage_policy_versions as v', 'v.coverage_policy_version_id', '=', 'r.coverage_policy_version_id')->join('coverage_policies as p', 'p.coverage_policy_id', '=', 'v.coverage_policy_id')->join('nodes as n', 'n.node_id', '=', 'r.target_node_id')
            ->where(['r.hq_id' => $hqId, 'r.target' => $target, 'v.status' => 'PUBLISHED', 'n.status' => 'ACTIVE'])
            ->whereColumn('p.published_version_id', 'v.coverage_policy_version_id')
            ->where(fn ($q) => $q->whereNull('v.effective_from')->orWhere('v.effective_from', '<=', $at))
            ->where(fn ($q) => $q->whereNull('v.effective_to')->orWhere('v.effective_to', '>', $at))
            ->where(fn ($q) => $q->whereNull('r.offering_version_id')->when($offeringVersionId !== null, fn ($inner) => $inner->orWhere('r.offering_version_id', $offeringVersionId)))
            ->get(['r.*', 'v.coverage_policy_id', 'v.coverage_policy_version_id', 'v.version_number', 'p.policy_code', 'p.policy_title']);
        $matches = $rows->filter(fn ($row): bool => $this->matches($row, $input))->values();
        if ($matches->isEmpty()) throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'No published Coverage Rule matches the request.');
        $ranked = $matches->sortByDesc(fn ($row): string => sprintf('%011d-%d', (int) $row->priority + 100000, self::SPECIFICITY[$row->criterion_type]))->values();
        $best = $ranked->first();
        $ties = $ranked->filter(fn ($row): bool => (int) $row->priority === (int) $best->priority && self::SPECIFICITY[$row->criterion_type] === self::SPECIFICITY[$best->criterion_type]);
        if ($ties->count() > 1) throw new ApiException(ApiErrorCode::CoverageAmbiguous, 422, 'More than one published Coverage Rule has the best rank.', details: ['coverage_rule_ids' => $ties->pluck('coverage_rule_id')->all()]);
        return [
            'coverage_policy_id' => (string) $best->coverage_policy_id,
            'coverage_policy_version_id' => (string) $best->coverage_policy_version_id,
            'coverage_rule_id' => (string) $best->coverage_rule_id,
            'policy_code' => (string) $best->policy_code,
            'policy_title' => (string) $best->policy_title,
            'version_number' => (int) $best->version_number,
            'target_node_id' => (string) $best->target_node_id,
            'criterion_type' => (string) $best->criterion_type,
            'priority' => (int) $best->priority,
            'input' => $input,
            'matched_evidence' => $this->matchedEvidence($best, $input),
            'resolved_at' => $at->toISOString(),
        ];
    }

    /** @param array<string,mixed> $input @return array{geography:?array<string,mixed>,postal:?array<string,mixed>,geometry:?array<string,mixed>} */
    private function matchedEvidence(object $rule, array $input): array
    {
        $geography = match ($rule->criterion_type) {
            'PROVINCE' => ['province_id' => (string) $rule->province_id],
            'CITY' => [
                'province_id' => isset($input['province_id']) ? (string) $input['province_id'] : null,
                'city_id' => (string) $rule->city_id,
            ],
            default => null,
        };
        $postal = $rule->criterion_type === 'POSTAL_RANGE' ? [
            'postal_code' => (string) $input['postal_code'],
            'postal_code_from' => (string) $rule->postal_code_from,
            'postal_code_to' => (string) $rule->postal_code_to,
        ] : null;
        $geometry = match ($rule->criterion_type) {
            'POLYGON' => [
                'point' => ['latitude' => (float) $input['latitude'], 'longitude' => (float) $input['longitude']],
                'geometry' => json_decode((string) $rule->geometry_geojson, true, 512, JSON_THROW_ON_ERROR),
            ],
            'POINT_RADIUS' => [
                'point' => ['latitude' => (float) $input['latitude'], 'longitude' => (float) $input['longitude']],
                'center' => ['latitude' => (float) $rule->center_latitude, 'longitude' => (float) $rule->center_longitude],
                'radius_meters' => (int) $rule->radius_meters,
            ],
            default => null,
        };

        return ['geography' => $geography, 'postal' => $postal, 'geometry' => $geometry];
    }

    /** @param array<string,mixed> $input */
    private function matches(object $rule, array $input): bool
    {
        return match ($rule->criterion_type) {
            'PROVINCE' => ($input['province_id'] ?? null) === $rule->province_id,
            'CITY' => ($input['city_id'] ?? null) === $rule->city_id,
            'POSTAL_RANGE' => isset($input['postal_code']) && strcmp($input['postal_code'], $rule->postal_code_from) >= 0 && strcmp($input['postal_code'], $rule->postal_code_to) <= 0,
            'POLYGON' => isset($input['latitude'], $input['longitude']) && GeoJsonGeometry::contains(json_decode($rule->geometry_geojson, true, 512, JSON_THROW_ON_ERROR), (float) $input['latitude'], (float) $input['longitude']),
            'POINT_RADIUS' => isset($input['latitude'], $input['longitude']) && GeoJsonGeometry::withinRadius((float) $input['latitude'], (float) $input['longitude'], (float) $rule->center_latitude, (float) $rule->center_longitude, (int) $rule->radius_meters),
            default => false,
        };
    }

    /** @param list<array<string,mixed>> $rules */
    private function validateRuleInputs(string $hq, array $rules): void
    {
        if ($rules === []) throw new ApiException(ApiErrorCode::ValidationError, 422, 'At least one Coverage Rule is required.');
        $signatures = [];
        foreach ($rules as $rule) {
            if (! in_array($rule['target'] ?? null, ['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE'], true) || ! isset($rule['target_node_id'], $rule['priority'], $rule['criterion']['criterion_type'])) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every Coverage Rule is incomplete.');
            if ((int) $rule['priority'] < -100000 || (int) $rule['priority'] > 100000) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Coverage priority is outside the supported range.');
            if (! DB::table('nodes')->where(['hq_id' => $hq, 'node_id' => $rule['target_node_id'], 'status' => 'ACTIVE'])->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every target Node must be active and belong to the current HQ.');
            if (($rule['offering_version_id'] ?? null) !== null && ! DB::table('service_offering_versions as v')->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')->where('v.service_offering_version_id', $rule['offering_version_id'])->where(fn ($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $hq))->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Offering Version is not visible to this HQ.');
            $criterion = $this->normalizeCriterion($rule['criterion']);
            $signature = hash('sha256', json_encode([$rule['target'], (int) $rule['priority'], $rule['offering_version_id'] ?? null, $criterion], JSON_THROW_ON_ERROR));
            if (isset($signatures[$signature])) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Duplicate indistinguishable Coverage Rules are not allowed.');
            $signatures[$signature] = true;
        }
    }

    /** @param array<string,mixed> $criterion @return array<string,mixed> */
    private function normalizeCriterion(array $criterion): array
    {
        $type = $criterion['criterion_type'] ?? null;
        if ($type === 'PROVINCE' && isset($criterion['province_id']) && DB::table('provinces')->where('province_id', $criterion['province_id'])->exists()) return ['criterion_type' => $type, 'province_id' => $criterion['province_id']];
        if ($type === 'CITY' && isset($criterion['city_id']) && DB::table('cities')->where('city_id', $criterion['city_id'])->exists()) return ['criterion_type' => $type, 'city_id' => $criterion['city_id']];
        if ($type === 'POSTAL_RANGE' && preg_match('/^\d{10}$/', (string) ($criterion['postal_code_from'] ?? '')) && preg_match('/^\d{10}$/', (string) ($criterion['postal_code_to'] ?? '')) && strcmp($criterion['postal_code_from'], $criterion['postal_code_to']) <= 0) return ['criterion_type' => $type, 'postal_code_from' => $criterion['postal_code_from'], 'postal_code_to' => $criterion['postal_code_to']];
        if ($type === 'POLYGON' && is_array($criterion['geometry'] ?? null)) return ['criterion_type' => $type, 'geometry' => GeoJsonGeometry::normalize($criterion['geometry'])];
        if ($type === 'POINT_RADIUS' && isset($criterion['center']['latitude'], $criterion['center']['longitude'], $criterion['radius_meters']) && $criterion['center']['latitude'] >= -90 && $criterion['center']['latitude'] <= 90 && $criterion['center']['longitude'] >= -180 && $criterion['center']['longitude'] <= 180 && $criterion['radius_meters'] >= 1 && $criterion['radius_meters'] <= 500000) return ['criterion_type' => $type, 'center' => ['latitude' => (float) $criterion['center']['latitude'], 'longitude' => (float) $criterion['center']['longitude']], 'radius_meters' => (int) $criterion['radius_meters']];
        throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Coverage criterion is invalid.');
    }

    /** @param list<array<string,mixed>> $rules */
    private function replaceRules(string $hq, string $versionId, array $rules): void
    {
        DB::table('coverage_rules')->where('coverage_policy_version_id', $versionId)->delete();
        foreach ($rules as $rule) {
            $criterion = $this->normalizeCriterion($rule['criterion']); $type = $criterion['criterion_type'];
            DB::table('coverage_rules')->insert(['coverage_rule_id' => (string) Str::uuid(), 'hq_id' => $hq, 'coverage_policy_version_id' => $versionId, 'target' => $rule['target'], 'target_node_id' => $rule['target_node_id'], 'priority' => $rule['priority'], 'offering_version_id' => $rule['offering_version_id'] ?? null, 'criterion_type' => $type, 'province_id' => $criterion['province_id'] ?? null, 'city_id' => $criterion['city_id'] ?? null, 'postal_code_from' => $criterion['postal_code_from'] ?? null, 'postal_code_to' => $criterion['postal_code_to'] ?? null, 'geometry_geojson' => isset($criterion['geometry']) ? json_encode($criterion['geometry'], JSON_THROW_ON_ERROR) : null, 'center_latitude' => $criterion['center']['latitude'] ?? null, 'center_longitude' => $criterion['center']['longitude'] ?? null, 'radius_meters' => $criterion['radius_meters'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** @return list<array<string,mixed>> */
    private function ruleInputs(string $hq, string $versionId): array
    {
        if (! DB::table('coverage_policy_versions')->where(['hq_id' => $hq, 'coverage_policy_version_id' => $versionId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Source version not found.');
        return DB::table('coverage_rules')->where('coverage_policy_version_id', $versionId)->orderBy('created_at')->get()->map(fn ($row): array => array_diff_key($this->ruleArray($row), ['coverage_rule_id' => true]))->all();
    }

    private function versionRow(string $hq, string $policy, string $version): object
    {
        $row = DB::table('coverage_policy_versions')->where(['hq_id' => $hq, 'coverage_policy_id' => $policy, 'coverage_policy_version_id' => $version])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $row;
    }
    private function lockedVersion(string $hq, string $policy, string $version): object { $row = DB::table('coverage_policy_versions')->where(['hq_id' => $hq, 'coverage_policy_id' => $policy, 'coverage_policy_version_id' => $version])->lockForUpdate()->first(); if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'); return $row; }
    private function expected(object $row, int $expected): void { if ((int) $row->version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Coverage Version is stale.', details: ['current_version' => (int) $row->version]); }

    /** @return array<string,mixed> */
    private function validatedChanges(string $hq, object $row, string $user): array { if ($row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft can be validated.'); $this->validateRuleInputs($hq, $this->ruleInputs($hq, $row->coverage_policy_version_id)); $this->assertDates($row); return ['status' => 'VALIDATED', 'validated_by' => $user, 'validated_at' => now()]; }
    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private function simpleChanges(object $row, string $from, string $to, array $extra = []): array { if ($row->status !== $from) throw new ApiException(ApiErrorCode::ValidationError, 422, "Only {$from} can transition to {$to}."); return ['status' => $to, ...$extra]; }
    /** @return array<string,mixed> */
    private function archiveChanges(object $row): array { if (! in_array($row->status, ['DRAFT', 'VALIDATED', 'APPROVED'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published or superseded versions cannot be archived.'); return ['status' => 'ARCHIVED']; }
    /** @return array<string,mixed> */
    private function publishedChanges(string $hq, object $row, string $user): array { if ($row->status !== 'APPROVED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.'); $this->assertDates($row); $overlap = DB::table('coverage_policy_versions')->where(['hq_id' => $hq, 'coverage_policy_id' => $row->coverage_policy_id, 'status' => 'PUBLISHED'])->where('coverage_policy_version_id', '!=', $row->coverage_policy_version_id)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $row->effective_from ?? now()))->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<', $row->effective_to ?? Carbon::parse('9999-12-31')))->exists(); if ($overlap) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published Coverage Version effective dates cannot overlap.'); $detail = $this->versionArray($row); return ['status' => 'PUBLISHED', 'published_by' => $user, 'published_at' => now(), 'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR))]; }
    private function assertDates(object $row): void { if ($row->effective_from !== null && $row->effective_to !== null && Carbon::parse($row->effective_from)->gte(Carbon::parse($row->effective_to))) throw new ApiException(ApiErrorCode::ValidationError, 422, 'effective_to must be after effective_from.'); }

    /** @return array<string,mixed> */
    private function policyArray(object $row): array { return ['coverage_policy_id' => (string) $row->coverage_policy_id, 'policy_code' => (string) $row->policy_code, 'policy_title' => (string) $row->policy_title, 'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null]; }
    /** @return array<string,mixed> */
    private function versionArray(object $row): array { return ['coverage_policy_version_id' => (string) $row->coverage_policy_version_id, 'coverage_policy_id' => (string) $row->coverage_policy_id, 'version_number' => (int) $row->version_number, 'status' => (string) $row->status, 'effective_from' => $row->effective_from, 'effective_to' => $row->effective_to, 'version' => (int) $row->version, 'rules' => DB::table('coverage_rules')->where('coverage_policy_version_id', $row->coverage_policy_version_id)->orderByDesc('priority')->get()->map(fn ($rule): array => $this->ruleArray($rule))->all()]; }
    /** @return array<string,mixed> */
    private function ruleArray(object $row): array { $criterion = match ($row->criterion_type) { 'PROVINCE' => ['criterion_type' => 'PROVINCE', 'province_id' => (string) $row->province_id], 'CITY' => ['criterion_type' => 'CITY', 'city_id' => (string) $row->city_id], 'POSTAL_RANGE' => ['criterion_type' => 'POSTAL_RANGE', 'postal_code_from' => (string) $row->postal_code_from, 'postal_code_to' => (string) $row->postal_code_to], 'POLYGON' => ['criterion_type' => 'POLYGON', 'geometry' => json_decode($row->geometry_geojson, true, 512, JSON_THROW_ON_ERROR)], default => ['criterion_type' => 'POINT_RADIUS', 'center' => ['latitude' => (float) $row->center_latitude, 'longitude' => (float) $row->center_longitude], 'radius_meters' => (int) $row->radius_meters] }; return ['coverage_rule_id' => (string) $row->coverage_rule_id, 'target' => (string) $row->target, 'target_node_id' => (string) $row->target_node_id, 'priority' => (int) $row->priority, 'offering_version_id' => $row->offering_version_id ? (string) $row->offering_version_id : null, 'criterion' => $criterion]; }

    private function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $status, string $correlationId, ?string $note = null): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, safeNote: $note, sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, $type, $id, 'network.configuration.changed', $correlationId, ['action' => $action, 'target_type' => $type, 'target_id' => $id, 'status' => $status]);
    }
}
