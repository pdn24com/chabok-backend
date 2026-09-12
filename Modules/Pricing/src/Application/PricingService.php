<?php

declare(strict_types=1);

namespace Modules\Pricing\Application;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Geography\Application\GeographyResolver;
use Modules\Geography\Application\PolygonGeometry;
use Modules\Pricing\Domain\PostalRange;
use Modules\Geography\Domain\PersianSearchNormalizer;
use Modules\Pricing\Domain\DeterministicCalculator;
use Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolver;

final readonly class PricingService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private ServiceEligibilityResolver $catalog,
        private DeterministicCalculator $calculator,
        private TariffMatrixCompiler $matrixCompiler,
        private \Modules\Pricing\Domain\FreightMatrices $matrices,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private GeographyResolver $geography,
        private PersianSearchNormalizer $normalizer,
        private PolygonGeometry $polygons,
    ) {}

    /** @param array<string,mixed> $filters */
    public function listTariffs(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'pricing.tariff.view');
        $query = DB::table('tariff_families as f')->where(fn ($q) => $q->whereNull('f.hq_id')->orWhere('f.hq_id', $actor->hqId))
            ->select(['f.*'])->selectSub(DB::table('tariff_versions as v')->select('v.status')->whereColumn('v.tariff_family_id', 'f.tariff_family_id')->orderByDesc('v.version_number')->limit(1), 'latest_status')
            ->selectSub(DB::table('tariff_versions as v')->select('v.version_number')->whereColumn('v.tariff_family_id', 'f.tariff_family_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_number');
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('f.code', 'like', $search)->orWhere('f.title', 'like', $search));
        }
        return $query->orderBy('f.code')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
    }

    /** @param array<string,mixed> $filters */
    public function listZoneSets(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'pricing.tariff.view');
        $query = DB::table('pricing_zone_sets as s')->where(fn ($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $actor->hqId))
            ->select(['s.*'])->selectSub(DB::table('pricing_zone_set_versions as v')->select('v.status')->whereColumn('v.pricing_zone_set_id', 's.pricing_zone_set_id')->orderByDesc('v.version_number')->limit(1), 'latest_status')
            ->selectSub(DB::table('pricing_zone_set_versions as v')->select('v.version_number')->whereColumn('v.pricing_zone_set_id', 's.pricing_zone_set_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_number');
        if (($filters['search'] ?? '') !== '') $query->where(fn ($q) => $q->where('s.code', 'like', '%'.addcslashes((string) $filters['search'], '%_\\').'%')->orWhere('s.title', 'like', '%'.addcslashes((string) $filters['search'], '%_\\').'%'));
        return $query->orderBy('s.code')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
    }

    /** @param array<string,mixed> $filters */
    public function listZoneSetVersionReferences(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'pricing.tariff.view');
        $includeVersionId = (string) ($filters['include_version_id'] ?? '');
        $query = DB::table('pricing_zone_set_versions as v')
            ->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')
            ->where(fn ($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $actor->hqId));
        $search = ($filters['search'] ?? '') === '' ? null : '%'.addcslashes((string) $filters['search'], '%_\\').'%';
        $query->where(function ($q) use ($includeVersionId, $search): void {
            $q->where(function ($published) use ($search): void {
                $published->where('v.status', 'PUBLISHED');
                if ($search !== null) $published->where(fn ($match) => $match->where('s.code', 'like', $search)->orWhere('s.title', 'like', $search));
            });
            if ($includeVersionId !== '') $q->orWhere('v.zone_set_version_id', $includeVersionId);
        });
        $page = $query->select([
            'v.zone_set_version_id', 'v.pricing_zone_set_id', 'v.version_number', 'v.status',
            'v.valid_from', 'v.valid_to', 's.code', 's.title', 's.purpose',
        ])->orderByRaw('v.zone_set_version_id = ? DESC', [$includeVersionId ?: '00000000-0000-0000-0000-000000000000'])
            ->orderBy('s.title')->orderByDesc('v.version_number')
            ->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 50))), page: max(1, (int) ($filters['page'] ?? 1)));
        $page->setCollection($page->getCollection()->map(function ($row): array {
            $reference = (array) $row;
            $reference['zones'] = DB::table('pricing_zones')
                ->where('zone_set_version_id', $row->zone_set_version_id)
                ->orderBy('title')->get(['pricing_zone_id', 'code', 'title', 'remote_area', 'rank'])
                ->map(fn ($zone) => [...(array) $zone, 'remote_area' => (bool) $zone->remote_area])->all();

            return $reference;
        }));

        return $page;
    }

    /** @return list<array<string,mixed>> */
    public function listChargeTypes(AuthenticatedPrincipal $actor): array
    {
        $this->assertAccess($actor, 'pricing.tariff.view');
        return DB::table('pricing_charge_types')->orderBy('code')->get()->map(fn ($row) => (array) $row)->all();
    }

    /** @param array<string,mixed> $filters */
    public function auditEvents(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'pricing.audit.view');
        $query = DB::table('audit_events')->where('hq_id', $actor->hqId)->where('action_key', 'like', 'PRICING_%');
        if (! empty($filters['target_id'])) $query->where('target_id', $filters['target_id']);
        return $query->orderByDesc('created_at')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
    }

    /** @return list<array<string,mixed>> */
    public function history(AuthenticatedPrincipal $actor, string $kind, string $identityId): array
    {
        $this->assertAccess($actor, 'pricing.tariff.view');
        [$identityTable, $versionTable, $parentId, $versionId] = $this->pricingMap($kind);
        if (! DB::table($identityTable)->where($parentId, $identityId)->where(fn ($q) => $q->whereNull('hq_id')->orWhere('hq_id', $actor->hqId))->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return DB::table($versionTable)->where($parentId, $identityId)->orderByDesc('version_number')->pluck($versionId)
            ->map(function ($id) use ($kind, $actor): array {
                if ($kind !== 'tariffs') return $this->zoneVersion($actor, (string) $id);
                $version = $this->tariffVersion($actor, (string) $id);
                $version['configured_zone_set'] = $this->zoneVersion($actor, $version['zone_set_version_id']);
                $asOf = CarbonImmutable::now('UTC');
                $version['zone_resolution_at'] = $asOf->toISOString();
                $version['effective_zone_set'] = null;
                $version['zone_resolution_error'] = null;
                try {
                    $effectiveId = $this->resolveEffectiveZoneSetVersion($version['zone_set_version_id'], $asOf);
                    $version['effective_zone_set'] = $this->zoneVersion($actor, $effectiveId);
                } catch (ApiException $error) {
                    $version['zone_resolution_error'] = 'PRICING_ZONE_VERSION_UNAVAILABLE_OR_AMBIGUOUS';
                }
                return $version;
            })->all();
    }

    /** @return array<string,mixed> */
    public function cloneDraft(AuthenticatedPrincipal $actor, string $kind, string $identityId, string $correlationId): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        [$identityTable, $versionTable, $parentId, $versionId] = $this->pricingMap($kind);
        return $this->transactions->run(function () use ($actor, $kind, $identityId, $correlationId, $identityTable, $versionTable, $parentId, $versionId): array {
            if (! DB::table($identityTable)->where([$parentId => $identityId, 'hq_id' => $actor->hqId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $previous = (array) DB::table($versionTable)->where($parentId, $identityId)->orderByDesc('version_number')->lockForUpdate()->first();
            if ($previous === []) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if (DB::table($versionTable)->where($parentId, $identityId)->whereIn('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED'])->exists()) throw new ApiException(ApiErrorCode::Conflict, 409, 'An unpublished successor already exists.');
            $previousId = (string) $previous[$versionId]; $newId = (string) Str::uuid();
            unset($previous[$versionId], $previous['approved_by'], $previous['published_by'], $previous['approved_at'], $previous['published_at'], $previous['content_digest']);
            $previous[$versionId] = $newId; $previous['previous_version_id'] = $previousId; $previous['version_number'] = ((int) $previous['version_number']) + 1; $previous['status'] = 'DRAFT'; $previous['lock_version'] = 1; $previous['valid_from'] = null; $previous['valid_to'] = null; $previous['created_by'] = $actor->userId; $previous['created_at'] = now(); $previous['updated_at'] = now();
            DB::table($versionTable)->insert($previous);
            if ($kind === 'tariffs') {
                $rules = DB::table('tariff_rate_rules')->where('tariff_version_id', $previousId)->get()->map(fn ($row) => $this->decode((array) $row))->all();
                $this->replaceRules($newId, $rules);
            } else {
                $this->replaceZones($newId, $this->zoneVersion($actor, $previousId)['zones'], $previousId);
            }
            $this->record($actor, 'PRICING_DRAFT_CLONED', 'PRICING_VERSION', $newId, $correlationId, ['previous_version_id' => $previousId]);
            return $kind === 'tariffs' ? $this->tariffVersion($actor, $newId) : $this->zoneVersion($actor, $newId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createChargeType(AuthenticatedPrincipal $actor, array $input): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        $id = (string) Str::uuid();
        DB::table('pricing_charge_types')->insert(['charge_type_id' => $id, ...$input, 'created_at' => now(), 'updated_at' => now()]);
        return (array) DB::table('pricing_charge_types')->where('charge_type_id', $id)->first();
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createZoneSet(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $setId = (string) Str::uuid(); $versionId = (string) Str::uuid(); $now = now();
            DB::table('pricing_zone_sets')->insert(['pricing_zone_set_id' => $setId, 'hq_id' => $actor->hqId, 'owner_key' => $actor->hqId, 'code' => Str::upper($input['code']), 'purpose' => $input['purpose'], 'title' => $input['title'], 'created_by' => $actor->userId, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('pricing_zone_set_versions')->insert(['zone_set_version_id' => $versionId, 'pricing_zone_set_id' => $setId, 'hq_id' => $actor->hqId, 'version_number' => 1, 'status' => 'DRAFT', 'valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null), 'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null), 'lock_version' => 1, 'created_by' => $actor->userId, 'created_at' => $now, 'updated_at' => $now]);
            $this->replaceZones($versionId, (array) $input['zones']);
            $this->record($actor, 'PRICING_ZONE_SET_CREATED', 'PRICING_ZONE_SET', $setId, $correlationId, ['version_id' => $versionId]);
            return $this->zoneVersion($actor, $versionId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateZoneVersion(AuthenticatedPrincipal $actor, string $versionId, array $input): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        return $this->transactions->run(function () use ($actor, $versionId, $input): array {
            $row = DB::table('pricing_zone_set_versions')->where('zone_set_version_id', $versionId)->where('hq_id', $actor->hqId)->lockForUpdate()->first();
            $this->assertDraft($row, (int) $input['expected_version']);
            DB::table('pricing_zone_set_versions')->where('zone_set_version_id', $versionId)->update(['valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null), 'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null), 'lock_version' => ((int) $row->lock_version) + 1, 'updated_at' => now()]);
            $this->replaceZones($versionId, (array) $input['zones']);
            $this->record($actor, 'PRICING_ZONE_DRAFT_UPDATED', 'PRICING_VERSION', $versionId, (string) Str::uuid(), ['lock_version' => ((int) $row->lock_version) + 1]);
            return $this->zoneVersion($actor, $versionId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createTariff(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        if ($input['currency'] !== 'IRR') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Milestone 1 supports IRR only.');
        if (! in_array((string) ($input['scope_type'] ?? 'TENANT'), ['TENANT', 'PLATFORM'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected tariff scope has no authoritative reference directory.');
        $input = $this->matrixCompiler->prepare($input, $this->zoneVersion($actor, (string) $input['zone_set_version_id'])['zones'], $actor->hqId);
        $this->assertTariffReferences($actor, $input);
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $familyId = (string) Str::uuid(); $versionId = (string) Str::uuid(); $now = now();
            $scopeType = (string) ($input['scope_type'] ?? 'TENANT');
            $scopeValue = match ($scopeType) {
                'TENANT' => $actor->hqId,
                'PLATFORM' => null,
                default => $input['scope_value'] ?? null,
            };
            DB::table('tariff_families')->insert(['tariff_family_id' => $familyId, 'hq_id' => $actor->hqId, 'owner_key' => $actor->hqId, 'code' => Str::upper($input['code']), 'title' => isset($input['title']) ? trim((string) $input['title']) : null, 'purpose' => $input['purpose'], 'scope_type' => $scopeType, 'scope_value' => $scopeValue, 'currency' => 'IRR', 'priority' => $input['priority'] ?? 100, 'created_by' => $actor->userId, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('tariff_versions')->insert(['tariff_version_id' => $versionId, 'tariff_family_id' => $familyId, 'hq_id' => $actor->hqId, 'zone_set_version_id' => $input['zone_set_version_id'], 'zone_policy' => $input['zone_policy'], 'freight_matrices' => json_encode($input['freight_matrices'], JSON_THROW_ON_ERROR), 'version_number' => 1, 'status' => 'DRAFT', 'valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null), 'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null), 'lock_version' => 1, 'volumetric_divisor' => $input['volumetric_divisor'] ?? 5000, 'weight_rounding_step_kg' => $input['weight_rounding_step_kg'] ?? 0.5, 'rounding_mode' => $input['rounding_mode'] ?? 'STEP_UP', 'created_by' => $actor->userId, 'created_at' => $now, 'updated_at' => $now]);
            $this->replaceRules($versionId, (array) $input['rules']);
            $this->record($actor, 'TARIFF_FAMILY_CREATED', 'TARIFF_FAMILY', $familyId, $correlationId, ['version_id' => $versionId]);
            return $this->tariffVersion($actor, $versionId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateTariffVersion(AuthenticatedPrincipal $actor, string $versionId, array $input): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        $input = $this->matrixCompiler->prepare($input, $this->zoneVersion($actor, (string) $input['zone_set_version_id'])['zones'], $actor->hqId);
        $this->assertTariffReferences($actor, $input);
        return $this->transactions->run(function () use ($actor, $versionId, $input): array {
            $row = DB::table('tariff_versions')->where('tariff_version_id', $versionId)->where('hq_id', $actor->hqId)->lockForUpdate()->first();
            $this->assertDraft($row, (int) $input['expected_version']);
            DB::table('tariff_versions')->where('tariff_version_id', $versionId)->update(['zone_set_version_id' => $input['zone_set_version_id'], 'zone_policy' => $input['zone_policy'], 'freight_matrices' => json_encode($input['freight_matrices'], JSON_THROW_ON_ERROR), 'valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null), 'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null), 'volumetric_divisor' => $input['volumetric_divisor'] ?? 5000, 'weight_rounding_step_kg' => $input['weight_rounding_step_kg'] ?? 0.5, 'rounding_mode' => $input['rounding_mode'] ?? 'STEP_UP', 'lock_version' => ((int) $row->lock_version) + 1, 'updated_at' => now()]);
            $this->replaceRules($versionId, (array) $input['rules']);
            $this->record($actor, 'PRICING_DRAFT_UPDATED', 'PRICING_VERSION', $versionId, (string) Str::uuid(), ['lock_version' => ((int) $row->lock_version) + 1]);
            return $this->tariffVersion($actor, $versionId);
        });
    }

    /** @return array<string,mixed> */
    public function validateTariff(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        $version = $this->tariffVersion($actor, $versionId);
        $zones = $this->zoneVersion($actor, $version['zone_set_version_id'])['zones'];
        $errors = $this->matrices->validate($version['freight_matrices'] ?? [], array_column($zones, 'pricing_zone_id'), $version['zone_policy'], true);
        if ($version['zone_policy'] === 'HIGHER_ZONE_RANK' && ! $this->matrices->ranksValid($zones)) $errors[] = ['code' => 'PRICING_ZONE_RANK_INCOMPLETE', 'field' => 'zone_set_version_id'];
        if (! $version['valid_from']) $errors[] = ['code' => 'PRICING_VALID_FROM_REQUIRED', 'field' => 'valid_from'];
        if ($version['valid_from'] && $version['valid_to'] && $version['valid_to'] <= $version['valid_from']) $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        if ($this->hasVersionOverlap('tariff_versions', 'tariff_family_id', $version)) $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        if ($version['rules'] === []) $errors[] = ['code' => 'PRICING_RULE_NOT_FOUND', 'field' => 'rules'];
        if (! DB::table('pricing_zone_set_versions')->where('zone_set_version_id', $version['zone_set_version_id'])->where('status', 'PUBLISHED')->exists()) $errors[] = ['code' => 'PRICING_ZONE_VERSION_NOT_PUBLISHED', 'field' => 'zone_set_version_id'];
        foreach ($version['rules'] as $rule) {
            if (! DB::table('service_offering_versions')->where('service_offering_version_id', $rule['service_offering_version_id'])->where('status', 'PUBLISHED')->exists()) $errors[] = ['code' => 'PRICING_SERVICE_VERSION_NOT_PUBLISHED', 'field' => 'rules'];
            if ($rule['service_option_version_id'] !== null && ! DB::table('service_offering_option_rules')->where(['service_offering_version_id' => $rule['service_offering_version_id'], 'service_option_version_id' => $rule['service_option_version_id']])->exists()) $errors[] = ['code' => 'PRICING_SERVICE_OPTION_NOT_BOUND', 'field' => 'rules'];
            if ($rule['range_from'] !== null && $rule['range_to'] !== null && (float) $rule['range_from'] >= (float) $rule['range_to']) $errors[] = ['code' => 'PRICING_RANGE_INVALID', 'field' => 'rules'];
            $method = (string) $rule['calculation_method'];
            if ($method === 'FIXED' && $rule['fixed_amount'] === null) $errors[] = ['code' => 'PRICING_FIXED_AMOUNT_REQUIRED', 'field' => 'rules'];
            if (in_array($method, ['PER_UNIT', 'TIERED'], true) && $rule['unit_rate'] === null) $errors[] = ['code' => 'PRICING_UNIT_RATE_REQUIRED', 'field' => 'rules'];
            if ($method === 'SLAB' && $rule['fixed_amount'] === null && $rule['unit_rate'] === null) $errors[] = ['code' => 'PRICING_SLAB_RATE_REQUIRED', 'field' => 'rules'];
            if ($method === 'PERCENT' && $rule['percentage_bps'] === null) $errors[] = ['code' => 'PRICING_PERCENTAGE_REQUIRED', 'field' => 'rules'];
            if ($method === 'MIN_MAX' && $rule['minimum_amount'] === null && $rule['maximum_amount'] === null) $errors[] = ['code' => 'PRICING_MIN_MAX_BOUND_REQUIRED', 'field' => 'rules'];
            if ($rule['minimum_amount'] !== null && $rule['maximum_amount'] !== null && $rule['minimum_amount'] > $rule['maximum_amount']) $errors[] = ['code' => 'PRICING_MIN_MAX_INVALID', 'field' => 'rules'];
            if (($rule['amount_rounding_mode'] ?? 'NONE') !== 'NONE' && empty($rule['amount_rounding_step'])) $errors[] = ['code' => 'PRICING_AMOUNT_ROUNDING_STEP_REQUIRED', 'field' => 'rules'];
        }
        $duplicates = collect($version['rules'])->groupBy(fn ($r) => implode('|', [$r['service_offering_version_id'], $r['service_option_version_id'], $r['origin_zone_id'], $r['destination_zone_id'], $r['charge_type_id'], $r['priority'], $r['range_from'], $r['range_to']]))->filter(fn ($g) => $g->count() > 1);
        if ($duplicates->isNotEmpty()) $errors[] = ['code' => 'PRICING_RULE_AMBIGUOUS', 'field' => 'rules'];
        if ($this->hasAmbiguousRuleRanges($version['rules'])) $errors[] = ['code' => 'PRICING_RULE_RANGE_OVERLAP', 'field' => 'rules'];
        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /** @return array<string,mixed> */
    public function validateZoneSet(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        $version = $this->zoneVersion($actor, $versionId); $errors = [];
        try { $this->validatePolygons($version['zones']); } catch (ApiException $exception) { $errors[] = ['code' => 'PRICING_POLYGON_INVALID_OR_OVERLAPPING', 'field' => 'zones']; }
        if (! $version['valid_from']) $errors[] = ['code' => 'PRICING_VALID_FROM_REQUIRED', 'field' => 'valid_from'];
        if ($version['valid_from'] && $version['valid_to'] && $version['valid_to'] <= $version['valid_from']) $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        if ($this->hasVersionOverlap('pricing_zone_set_versions', 'pricing_zone_set_id', $version)) $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        if ($version['zones'] === []) $errors[] = ['code' => 'PRICING_ZONE_REQUIRED', 'field' => 'zones'];
        $members = collect($version['zones'])->flatMap(fn ($zone) => collect($zone['members'])->map(fn ($member) => [...$member, 'pricing_zone_id' => $zone['pricing_zone_id']]));
        $ambiguous = $members->where('member_type', '!=', 'POLYGON')->groupBy(fn ($member) => implode('|', [$member['member_type'], (string) ($member['city_id'] ?? $member['province_id'] ?? mb_strtolower((string) $member['reference_value'])), (string) ($member['range_end'] ?? ''), $this->memberPrecedence((string) $member['member_type'])]))
            ->contains(fn ($group) => $group->pluck('pricing_zone_id')->unique()->count() > 1);
        $postal = $members->where('member_type', 'POSTAL_RANGE')->values()->all();
        foreach ($postal as $index => $left) foreach (array_slice($postal, $index + 1) as $right) {
            if ($left['pricing_zone_id'] !== $right['pricing_zone_id'] && strlen((string) $left['reference_value']) === strlen((string) $right['reference_value']) && strcmp((string) $left['reference_value'], (string) $right['range_end']) <= 0 && strcmp((string) $right['reference_value'], (string) $left['range_end']) <= 0) $ambiguous = true;
        }
        if ($ambiguous) $errors[] = ['code' => 'PRICING_ZONE_AMBIGUOUS', 'field' => 'zones'];
        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /** @return array<string,mixed> */
    public function transition(AuthenticatedPrincipal $actor, string $kind, string $versionId, string $action, string $correlationId): array
    {
        $permission = $action === 'approve' ? 'pricing.tariff.approve' : 'pricing.tariff.publish'; $this->assertAccess($actor, $permission);
        $table = $kind === 'zone-sets' ? 'pricing_zone_set_versions' : 'tariff_versions'; $id = $kind === 'zone-sets' ? 'zone_set_version_id' : 'tariff_version_id';
        return $this->transactions->run(function () use ($actor, $kind, $versionId, $action, $correlationId, $table, $id): array {
            $row = DB::table($table)->where($id, $versionId)->where('hq_id', $actor->hqId)->lockForUpdate()->first();
            if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if ($action === 'approve') {
                if ((string) $row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft can be approved.');
                if ($kind === 'tariffs') { $validation = $this->validateTariff($actor, $versionId); if (! $validation['valid']) throw new ApiException(ApiErrorCode::ValidationError, 422, 'اعتبارسنجی تعرفه ناموفق بود؛ خطاها را پیش از تأیید اصلاح کنید.', details: $validation); }
                if ($kind === 'zone-sets' && ! $this->validateZoneSet($actor, $versionId)['valid']) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone Set validation failed.');
                $changes = ['status' => 'APPROVED', 'approved_by' => $actor->userId, 'approved_at' => now()];
            } elseif ($action === 'publish') {
                if ((string) $row->status !== 'APPROVED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.');
                $validation = $kind === 'zone-sets' ? $this->validateZoneSet($actor, $versionId) : $this->validateTariff($actor, $versionId);
                if (! $validation['valid']) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Pricing validation failed.', details: $validation);
                $detail = $kind === 'zone-sets' ? $this->zoneVersion($actor, $versionId) : $this->tariffVersion($actor, $versionId);
                $changes = ['status' => 'PUBLISHED', 'published_by' => $actor->userId, 'published_at' => now(), 'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR))];
            } elseif ($action === 'supersede') {
                if ((string) $row->status !== 'PUBLISHED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a published version can be superseded.');
                $changes = ['status' => 'SUPERSEDED'];
            } elseif ($action === 'archive') {
                if ((string) $row->status !== 'SUPERSEDED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a superseded version can be archived.');
                $changes = ['status' => 'ARCHIVED'];
            } else {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unsupported lifecycle action.');
            }
            DB::table($table)->where($id, $versionId)->update($changes + ['updated_at' => now()]);
            $this->record($actor, 'PRICING_VERSION_'.Str::upper($action).'D', 'PRICING_VERSION', $versionId, $correlationId, ['status' => $changes['status']]);
            return $kind === 'zone-sets' ? $this->zoneVersion($actor, $versionId) : $this->tariffVersion($actor, $versionId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function calculateQuote(AuthenticatedPrincipal $actor, array $input, string $idempotencyKey): array
    {
        return $this->calculate($actor, $input, $idempotencyKey);
    }

    public function simulateDraft(AuthenticatedPrincipal $actor, string $versionId, array $input, int $expectedVersion): array
    {
        $this->assertAccess($actor, 'pricing.tariff.manage_draft');
        return $this->transactions->run(function () use ($actor, $versionId, $input, $expectedVersion): array {
            $draft = DB::table('tariff_versions as v')->join('tariff_families as f', 'f.tariff_family_id', '=', 'v.tariff_family_id')
                ->where('v.tariff_version_id', $versionId)->where('v.hq_id', $actor->hqId)
                ->select(['v.*', 'f.currency', 'f.code as tariff_code', 'f.title as tariff_title'])->lockForUpdate()->first();
            if ($draft === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            $this->assertDraft($draft, $expectedVersion);
            return $this->calculate($actor, $input, '', $draft);
        });
    }

    private function calculate(AuthenticatedPrincipal $actor, array $input, string $idempotencyKey, ?object $draft = null): array
    {
        $this->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        $purpose = (string) ($input['purpose'] ?? 'SALES');
        if ($purpose !== 'SALES') throw new ApiException(ApiErrorCode::ValidationError, 422, 'This pricing purpose is not active in Milestone 1.', details: ['reason_code' => 'PRICING_PURPOSE_NOT_ACTIVE']);
        $input = $this->normalize($input); $inputFingerprint = $this->fingerprint($input);
        $existing = $draft === null ? DB::table('pricing_quotes')->where(['hq_id' => $actor->hqId, 'requested_by' => $actor->userId, 'idempotency_key' => $idempotencyKey])->first() : null;
        if ($existing !== null) {
            if ((string) $existing->input_fingerprint !== $inputFingerprint) throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'The idempotency key was already used with different pricing input.');
            return $this->quoteDetail($actor, (string) $existing->quote_id);
        }
        $offering = $this->catalog->validateSelection($actor, (string) $input['service_offering_id'], $input['service_offering_version_id'] ?? null, $input);
        $asOf = CarbonImmutable::parse((string) $input['as_of_timestamp'])->utc();
        $tariff = $draft ?? DB::table('tariff_versions as v')->join('tariff_families as f', 'f.tariff_family_id', '=', 'v.tariff_family_id')
            ->where('f.purpose', 'SALES')->where('f.currency', 'IRR')->where(fn ($q) => $q->whereNull('f.hq_id')->orWhere('f.hq_id', $actor->hqId))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('tariff_rate_rules as eligible_rule')->whereColumn('eligible_rule.tariff_version_id', 'v.tariff_version_id')->where('eligible_rule.service_offering_version_id', $offering['service_offering_version_id']))
            ->where(function ($q) use ($actor): void {
                $q->where(fn ($scope) => $scope->where('f.scope_type', 'PLATFORM')->whereNull('f.hq_id'))
                    ->orWhere(fn ($scope) => $scope->where('f.scope_type', 'TENANT')->where(fn ($value) => $value->whereNull('f.scope_value')->orWhere('f.scope_value', $actor->hqId)));
            })
            ->where('v.status', 'PUBLISHED')->where('v.valid_from', '<=', $asOf)->where(fn ($q) => $q->whereNull('v.valid_to')->orWhere('v.valid_to', '>', $asOf))
            ->orderBy('f.priority')->orderByRaw("FIELD(f.scope_type, 'CONTRACT', 'CUSTOMER', 'SEGMENT', 'TENANT', 'PLATFORM')")->orderBy('f.code')->orderByDesc('v.version_number')->select(['v.*', 'f.currency', 'f.code as tariff_code', 'f.title as tariff_title'])->first();
        if ($tariff === null) throw new ApiException(ApiErrorCode::PricingTariffNotFound, 422, 'No eligible tariff was found.', details: ['reason_code' => 'PRICING_TARIFF_NOT_FOUND']);
        $resolvedZoneSetVersionId = $this->resolveEffectiveZoneSetVersion((string) $tariff->zone_set_version_id, $asOf);
        [$origin, $originEvidence] = $this->resolveZone($resolvedZoneSetVersionId, (array) $input['sender'], 'sender');
        [$destination, $destinationEvidence] = $this->resolveZone($resolvedZoneSetVersionId, (array) $input['receiver'], 'receiver');
        $facts = $this->facts($input, (array) $tariff, $destination);
        $basisZone = $destination;
        if ($tariff->zone_policy === 'HIGHER_ZONE_RANK') {
            $rankedZones = DB::table('pricing_zones')->where('zone_set_version_id', $resolvedZoneSetVersionId)->get()->map(fn ($z) => (array) $z)->all();
            if (! $this->matrices->ranksValid($rankedZones)) throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'Zone ranks are incomplete or ambiguous.', details: ['reason_code' => 'PRICING_ZONE_RANK_INCOMPLETE']);
            $basisZone = $origin['rank'] > $destination['rank'] ? $origin : $destination;
        }
        $matrixCell = $this->matrixCell($tariff, $offering, $input, $origin, $basisZone, $facts['billable_weight_kg']);
        $rules = DB::table('tariff_rate_rules as r')->join('pricing_charge_types as c', 'c.charge_type_id', '=', 'r.charge_type_id')
            ->leftJoin('pricing_zones as origin_rule_zone', 'origin_rule_zone.pricing_zone_id', '=', 'r.origin_zone_id')
            ->leftJoin('pricing_zones as destination_rule_zone', 'destination_rule_zone.pricing_zone_id', '=', 'r.destination_zone_id')
            ->where('r.tariff_version_id', $tariff->tariff_version_id)->where('r.service_offering_version_id', $offering['service_offering_version_id'])
            ->where(fn ($q) => $q->whereNull('r.service_option_version_id')->orWhereIn('r.service_option_version_id', (array) $input['selected_option_version_ids']))
            ->select(['r.*', 'origin_rule_zone.code as origin_code', 'destination_rule_zone.code as destination_code', 'c.code as charge_type_code', 'c.category', 'c.accounting_mapping_key', 'c.code as title', DB::raw('COALESCE(r.taxable, c.taxable) as taxable')])->get()->filter(function ($r) use ($matrixCell, $origin, $destination): bool {
                if ($r->matrix_cell_id !== null) return $r->matrix_cell_id === $matrixCell;
                return ($r->origin_code === null || $r->origin_code === $origin['code']) && ($r->destination_code === null || $r->destination_code === $destination['code']);
            })->map(fn ($r) => (array) $r)->values()->all();
        if ($rules === []) throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'No pricing rule matches the selected service and lane.', details: ['reason_code' => 'PRICING_RULE_NOT_FOUND']);
        $calculation = $this->calculator->calculate($rules, $facts);
        if ($calculation['lines'] === [] || $calculation['total_amount'] <= 0 || ! collect($calculation['lines'])->contains(fn ($line) => $line['charge_code'] === 'BASE_FREIGHT')) throw new ApiException(ApiErrorCode::PricingRejected, 422, 'Pricing did not produce a complete nonzero base price.', details: ['reason_code' => 'PRICING_INCOMPLETE_RESULT']);
        if (($input['insurance_enabled'] ?? false) === true && ! collect($calculation['lines'])->contains(fn ($line) => $line['charge_code'] === 'INSURANCE')) throw new ApiException(ApiErrorCode::PricingRejected, 422, 'Mandatory insurance pricing is unavailable.', details: ['reason_code' => 'INSURANCE_PRICING_REQUIRED']);
        $quoteId = (string) Str::uuid(); $now = CarbonImmutable::now('UTC'); $ttl = (int) config('chabok.pricing.quote_ttl_seconds', 900);
        $evidence = ['tariff_code' => $tariff->tariff_code, 'zone_set' => ['configured_version_id' => (string) $tariff->zone_set_version_id, 'resolved_version_id' => $resolvedZoneSetVersionId], 'origin' => $originEvidence, 'destination' => $destinationEvidence, 'weight' => $facts, 'service' => ['outcome' => $offering['outcome'], 'reason_codes' => $offering['reason_codes'], 'labels' => $offering['labels'] ?? [], 'service_type_id' => $offering['service_type_id'], 'shipping_method_id' => $offering['shipping_method_id'], 'selected_option_version_ids' => $input['selected_option_version_ids'], 'commitment' => $offering['commitment'] ?? null]];
        $warnings = $facts['weight_evidence'] === 'AGGREGATE_FALLBACK' ? ['PRICING_AGGREGATE_WEIGHT_FALLBACK'] : [];
        $evidence['tariff_title'] = $tariff->tariff_title;
        $evidence['tariff_version_number'] = (int) $tariff->version_number;
        $evidence['zone_policy'] = $tariff->zone_policy;
        $evidence['zones'] = ['origin' => $origin, 'destination' => $destination, 'basis' => $basisZone];
        $evidence['matrix_cell_id'] = $matrixCell;
        if ($draft !== null) return [...$calculation, 'mode' => 'DRAFT', 'acceptable' => false, 'tariff_version_id' => $tariff->tariff_version_id, 'lock_version' => (int) $tariff->lock_version, 'zone_set_version_id' => $resolvedZoneSetVersionId, 'currency' => 'IRR', 'calculated_at' => $now->toISOString(), 'resolution_evidence' => $evidence, 'warnings' => $warnings, 'lines' => array_map(fn ($l) => [...$l, 'charge_type_code' => $l['charge_code']], $calculation['lines'])];
        $this->transactions->run(function () use ($actor, $input, $idempotencyKey, $inputFingerprint, $offering, $tariff, $resolvedZoneSetVersionId, $origin, $destination, $calculation, $quoteId, $now, $ttl, $evidence, $warnings): void {
            DB::table('pricing_quotes')->insert(['quote_id' => $quoteId, 'hq_id' => $actor->hqId, 'requested_by' => $actor->userId, 'purpose' => 'SALES', 'tariff_version_id' => $tariff->tariff_version_id, 'zone_set_version_id' => $resolvedZoneSetVersionId, 'service_offering_id' => $offering['service_offering_id'], 'service_offering_version_id' => $offering['service_offering_version_id'], 'origin_zone_id' => $origin['pricing_zone_id'], 'destination_zone_id' => $destination['pricing_zone_id'], 'currency' => 'IRR', 'subtotal_amount' => $calculation['subtotal_amount'], 'discount_amount' => $calculation['discount_amount'], 'tax_amount' => $calculation['tax_amount'], 'total_amount' => $calculation['total_amount'], 'normalized_input' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'resolution_evidence' => json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'warnings' => json_encode($warnings, JSON_THROW_ON_ERROR), 'input_fingerprint' => $inputFingerprint, 'result_fingerprint' => $calculation['result_fingerprint'], 'idempotency_key' => $idempotencyKey, 'status' => 'OFFERED', 'calculated_at' => $now, 'expires_at' => $now->addSeconds($ttl), 'created_at' => $now, 'updated_at' => $now]);
            $this->insertLines('pricing_quote_lines', 'quote_line_id', 'quote_id', $quoteId, $calculation['lines']);
        });
        return $this->quoteDetail($actor, $quoteId);
    }

    /** @return array<string,mixed> */
    public function quoteDetail(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        $this->assertAccess($actor, 'pricing.quote.view', runtime: true);
        $row = DB::table('pricing_quotes')->where(['quote_id' => $quoteId, 'hq_id' => $actor->hqId])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $result = $this->decode((array) $row); $result['lines'] = DB::table('pricing_quote_lines as l')->join('pricing_charge_types as c', 'c.charge_type_id', '=', 'l.charge_type_id')->where('l.quote_id', $quoteId)->orderBy('l.line_number')->get(['l.*', 'c.category'])->map(fn ($r) => $this->decode((array) $r))->all();
        return $result;
    }

    /** @return array<string,mixed> */
    public function rejectQuote(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        $this->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        DB::table('pricing_quotes')->where(['quote_id' => $quoteId, 'hq_id' => $actor->hqId, 'status' => 'OFFERED'])->update(['status' => 'REJECTED', 'updated_at' => now()]);
        return $this->quoteDetail($actor, $quoteId);
    }

    /** @return array<string,mixed> */
    public function acceptQuote(AuthenticatedPrincipal $actor, string $quoteId, string $objectType, string $objectId, string $inputFingerprint, string $idempotencyKey): array
    {
        $this->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        return $this->transactions->run(function () use ($actor, $quoteId, $objectType, $objectId, $inputFingerprint, $idempotencyKey): array {
            $existing = DB::table('pricing_snapshots')->where(['hq_id' => $actor->hqId, 'accepted_by' => $actor->userId, 'acceptance_idempotency_key' => $idempotencyKey])->first();
            if ($existing !== null) {
                if ((string) $existing->quote_id !== $quoteId || (string) $existing->object_type !== $objectType || (string) $existing->object_id !== $objectId) throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'The idempotency key was already used for another acceptance.');
                return $this->snapshotDetail((string) $existing->pricing_snapshot_id);
            }
            if ($objectType !== 'CONSIGNMENT' || ! DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $objectId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Pricing target not found.');
            $quote = DB::table('pricing_quotes')->where(['quote_id' => $quoteId, 'hq_id' => $actor->hqId])->lockForUpdate()->first();
            if ($quote === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if (CarbonImmutable::parse((string) $quote->expires_at)->isPast()) throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'The pricing quote has expired.');
            if ((string) $quote->input_fingerprint !== $inputFingerprint) throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'Pricing-relevant input changed.', details: ['reason_code' => 'PRICING_INPUT_CHANGED']);
            if ((string) $quote->status !== 'OFFERED') throw new ApiException(ApiErrorCode::Conflict, 409, 'The pricing quote is no longer available.');
            $snapshotId = (string) Str::uuid(); $now = now();
            DB::table('pricing_snapshots')->insert(['pricing_snapshot_id' => $snapshotId, 'hq_id' => $actor->hqId, 'quote_id' => $quoteId, 'object_type' => $objectType, 'object_id' => $objectId, 'purpose' => $quote->purpose, 'currency' => $quote->currency, 'subtotal_amount' => $quote->subtotal_amount, 'discount_amount' => $quote->discount_amount, 'tax_amount' => $quote->tax_amount, 'total_amount' => $quote->total_amount, 'input_fingerprint' => $quote->input_fingerprint, 'result_fingerprint' => $quote->result_fingerprint, 'acceptance_idempotency_key' => $idempotencyKey, 'accepted_by' => $actor->userId, 'accepted_at' => $now]);
            $lines = DB::table('pricing_quote_lines')->where('quote_id', $quoteId)->orderBy('line_number')->get()->map(fn ($r) => (array) $r)->all();
            $categories = DB::table('pricing_charge_types')->whereIn('charge_type_id', array_column($lines, 'charge_type_id'))->pluck('category', 'charge_type_id');
            $subtotal = collect($lines)->filter(fn ($line) => in_array($categories[$line['charge_type_id']] ?? null, ['BASE', 'SURCHARGE', 'COMMISSION'], true))->sum('amount');
            $discount = collect($lines)->filter(fn ($line) => ($categories[$line['charge_type_id']] ?? null) === 'DISCOUNT')->sum('amount');
            $tax = collect($lines)->filter(fn ($line) => ($categories[$line['charge_type_id']] ?? null) === 'TAX')->sum('amount');
            if ((int) $quote->subtotal_amount !== (int) $subtotal || (int) $quote->discount_amount !== (int) $discount || (int) $quote->tax_amount !== (int) $tax || (int) $quote->total_amount !== max(0, (int) $subtotal - (int) $discount + (int) $tax)) throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'Quote lines do not reconcile with totals.');
            foreach ($lines as $line) { unset($line['quote_line_id'], $line['quote_id']); $line['charge_line_id'] = (string) Str::uuid(); $line['pricing_snapshot_id'] = $snapshotId; DB::table('pricing_charge_lines')->insert($line); }
            DB::table('pricing_quotes')->where('quote_id', $quoteId)->update(['status' => 'ACCEPTED', 'accepted_at' => $now, 'updated_at' => $now]);
            return $this->snapshotDetail($snapshotId);
        });
    }

    /** @return array<string,mixed> */
    public function tariffVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $row = DB::table('tariff_versions as v')->join('tariff_families as f', 'f.tariff_family_id', '=', 'v.tariff_family_id')->where('v.tariff_version_id', $versionId)->where(fn ($q) => $q->whereNull('f.hq_id')->orWhere('f.hq_id', $actor->hqId))->select(['v.*', 'f.code', 'f.title', 'f.purpose', 'f.currency', 'f.scope_type', 'f.scope_value', 'f.priority'])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $result = $this->decode((array) $row); $result['rules'] = DB::table('tariff_rate_rules')->where('tariff_version_id', $versionId)->orderBy('priority')->get()->map(fn ($r) => $this->decode((array) $r))->all(); return $result;
    }

    /** @return array<string,mixed> */
    public function zoneVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $row = DB::table('pricing_zone_set_versions as v')->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')->where('v.zone_set_version_id', $versionId)->where(fn ($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $actor->hqId))->select(['v.*', 's.code', 's.title', 's.purpose'])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $result = (array) $row;
        $result['zones'] = DB::table('pricing_zones')->where('zone_set_version_id', $versionId)->get()->map(function ($z) {
            $zone = (array) $z;
            $zone['members'] = DB::table('pricing_zone_members as m')
                ->leftJoin('cities as c', 'c.city_id', '=', 'm.city_id')
                ->leftJoin('provinces as p', DB::raw('p.province_id'), '=', DB::raw('COALESCE(c.province_id, m.province_id)'))
                ->where('m.pricing_zone_id', $z->pricing_zone_id)
                ->select([
                    'm.zone_member_id', 'm.pricing_zone_id', 'm.member_type', 'm.reference_value',
                    'm.city_id', DB::raw('COALESCE(c.province_id, m.province_id) as province_id'),
                    'm.geometry', 'm.range_end', 'm.precedence', 'c.name_fa as city_name_fa', 'c.legacy_city_code',
                    'p.name_fa as province_name_fa', 'p.legacy_province_code',
                ])->get()->map(fn ($m) => [...(array) $m, 'geometry' => $m->geometry === null ? null : json_decode($m->geometry, true, 512, JSON_THROW_ON_ERROR)])->all();
            return $zone;
        })->all();
        return $result;
    }

    /** @return array<string,mixed> */
    private function snapshotDetail(string $snapshotId): array
    {
        $snapshot = (array) DB::table('pricing_snapshots')->where('pricing_snapshot_id', $snapshotId)->first();
        $snapshot['lines'] = DB::table('pricing_charge_lines')->where('pricing_snapshot_id', $snapshotId)->orderBy('line_number')->get()->map(fn ($row) => $this->decode((array) $row))->all();
        return $snapshot;
    }

    /** Validate the complete candidate before any deletion; the version lock serializes writers. */
    private function validatePolygons(array $zones): array
    {
        $polygons = [];
        foreach ($zones as $zi => &$zone) foreach ($zone['members'] as &$member) {
            if ($member['member_type'] !== 'POLYGON') { unset($member['geometry']); continue; }
            $member['geometry'] = $this->polygons->normalize((array) ($member['geometry'] ?? []));
            foreach ($polygons as [$otherZone, $geometry]) {
                if ($otherZone !== $zi && $this->polygons->intersects($geometry, $member['geometry'])) throw new ApiException(ApiErrorCode::PricingZoneAmbiguous, 422, 'محدوده با چندضلعی منطقهٔ دیگری تداخل دارد؛ پیش از ذخیره مرزها را اصلاح کنید.', details: ['reason_code' => 'PRICING_POLYGON_OVERLAP']);
            }
            $polygons[] = [$zi, $member['geometry']];
        }
        unset($zone, $member);
        return $zones;
    }

    /** @param list<array<string,mixed>> $zones */
    private function replaceZones(string $versionId, array $zones, ?string $sourceVersionId = null): void
    {
        $savedPostal = DB::table('pricing_zone_members as m')->join('pricing_zones as z', 'z.pricing_zone_id', '=', 'm.pricing_zone_id')
            ->where('z.zone_set_version_id', $sourceVersionId ?? $versionId)->where('m.member_type', 'POSTAL_RANGE')->get(['z.pricing_zone_id', 'm.reference_value', 'm.range_end']);
        foreach ($zones as &$zone) foreach ($zone['members'] as &$member) {
            if ($member['member_type'] === 'POSTAL_RANGE') {
                $from = PostalRange::normalize((string) ($member['reference_value'] ?? ''));
                $to = PostalRange::normalize((string) ($member['range_end'] ?? ''));
                $savedIndex = $savedPostal->search(fn ($old) => $old->pricing_zone_id === ($zone['pricing_zone_id'] ?? null) && $old->reference_value === $from && $old->range_end === $to);
                $unchanged = $savedIndex !== false;
                if ($unchanged) $savedPostal->forget($savedIndex);
                if (! $unchanged && ! PostalRange::valid($from, $to)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'ابتدا و انتهای بازهٔ کدپستی باید دقیقاً ده رقم و به‌ترتیب باشند.', details: ['reason_code' => 'PRICING_POSTAL_RANGE_INVALID']);
                $member['reference_value'] = $from; $member['range_end'] = $to;
            }
        }
        unset($zone, $member);
        $zones = $this->validatePolygons($zones);
        $codes = []; $ranks = [];
        foreach ($zones as $zone) {
            $code = Str::upper($zone['code']); $rank = $zone['rank'] ?? null;
            if (isset($codes[$code]) || ($rank !== null && ($rank < 1 || (int) $rank != $rank || isset($ranks[(int) $rank])))) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone codes and explicit ranks must be unique within the version.');
            $codes[$code] = true; if ($rank !== null) $ranks[(int) $rank] = true;
        }
        $existing = DB::table('pricing_zones')->where('zone_set_version_id', $versionId)->pluck('pricing_zone_id', 'code')->all();
        DB::table('pricing_zones')->where('zone_set_version_id', $versionId)->delete();
        foreach ($zones as $zone) {
            $requestedId = $zone['pricing_zone_id'] ?? null;
            $zoneId = in_array($requestedId, $existing, true) ? $requestedId : ($existing[Str::upper($zone['code'])] ?? (string) Str::uuid());
            DB::table('pricing_zones')->insert(['pricing_zone_id' => $zoneId, 'zone_set_version_id' => $versionId, 'code' => Str::upper($zone['code']), 'title' => $zone['title'], 'remote_area' => $zone['remote_area'] ?? false, 'rank' => $zone['rank'] ?? null]);
            foreach ((array) ($zone['members'] ?? []) as $member) {
                $type = (string) $member['member_type'];
                $city = $type === 'CITY' ? $this->canonicalCityMember($member) : null;
                $cityId = $city['city_id'] ?? null;
                $provinceId = $type === 'PROVINCE' ? (string) ($member['province_id'] ?? '') : null;
                if ($type === 'PROVINCE' && ($provinceId === '' || ! DB::table('provinces')->where('province_id', $provinceId)->where('is_active', true)->exists())) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone member province is inactive or invalid.');
                }
                $reference = $cityId ?? $provinceId ?? (string) ($member['reference_value'] ?? '');
                DB::table('pricing_zone_members')->insert(['zone_member_id' => (string) Str::uuid(), 'pricing_zone_id' => $zoneId, 'member_type' => $type, 'reference_value' => $reference, 'city_id' => $cityId, 'province_id' => $provinceId, 'range_end' => $member['range_end'] ?? null, 'geometry' => isset($member['geometry']) ? json_encode($member['geometry'], JSON_THROW_ON_ERROR) : null, 'precedence' => $this->memberPrecedence($type)]);
            }
        }
    }

    /** @param list<array<string,mixed>> $rules */
    private function replaceRules(string $versionId, array $rules): void
    {
        DB::table('tariff_rate_rules')->where('tariff_version_id', $versionId)->delete();
        foreach ($rules as $rule) DB::table('tariff_rate_rules')->insert(['rate_rule_id' => (string) Str::uuid(), 'matrix_cell_id' => $rule['matrix_cell_id'] ?? null, 'taxable' => $rule['taxable'] ?? null, 'tariff_version_id' => $versionId, 'service_offering_version_id' => $rule['service_offering_version_id'], 'service_option_version_id' => $rule['service_option_version_id'] ?? null, 'charge_type_id' => $rule['charge_type_id'], 'origin_zone_id' => $rule['origin_zone_id'] ?? null, 'destination_zone_id' => $rule['destination_zone_id'] ?? null, 'calculation_method' => $rule['calculation_method'], 'basis' => $rule['basis'] ?? 'BILLABLE_WEIGHT', 'range_from' => $rule['range_from'] ?? null, 'range_to' => $rule['range_to'] ?? null, 'fixed_amount' => $rule['fixed_amount'] ?? null, 'unit_rate' => $rule['unit_rate'] ?? null, 'incremental_step_kg' => $rule['incremental_step_kg'] ?? null, 'percentage_bps' => $rule['percentage_bps'] ?? null, 'minimum_amount' => $rule['minimum_amount'] ?? null, 'maximum_amount' => $rule['maximum_amount'] ?? null, 'amount_rounding_mode' => $rule['amount_rounding_mode'] ?? 'NONE', 'amount_rounding_step' => $rule['amount_rounding_step'] ?? null, 'basis_charge_codes' => isset($rule['basis_charge_codes']) ? json_encode($rule['basis_charge_codes'], JSON_THROW_ON_ERROR) : null, 'conditions' => isset($rule['conditions']) ? json_encode($rule['conditions'], JSON_THROW_ON_ERROR) : null, 'priority' => $rule['priority'] ?? 100]);
    }

    /** @param array<string,mixed> $party @return array{array<string,mixed>,array<string,mixed>} */
    private function resolveZone(string $versionId, array $party, string $partyName): array
    {
        $members = DB::table('pricing_zone_members as m')->join('pricing_zones as z', 'z.pricing_zone_id', '=', 'm.pricing_zone_id')->where('z.zone_set_version_id', $versionId)->get(); $matches = [];
        if ($members->contains('member_type', 'POLYGON')) {
            foreach (['latitude' => 90, 'longitude' => 180] as $coordinate => $limit) {
                $value = $party[$coordinate] ?? null;
                if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > $limit) throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'تعرفهٔ انتخاب‌شده به موقعیت دقیق نیاز دارد؛ موقعیت مبدأ و مقصد را روی نقشه ثبت کنید.', fieldErrors: ["{$partyName}.{$coordinate}" => ['موقعیت دقیق نشانی الزامی است.']], details: ['reason_code' => 'PRICING_COORDINATES_REQUIRED', 'party' => $partyName]);
            }
        }
        foreach ($members as $m) { $matchesMember = match ($m->member_type) { 'EXPLICIT_OVERRIDE' => ($party['zone_override'] ?? null) === $m->reference_value, 'POSTAL_RANGE' => isset($party['postal_code']) && strcmp((string) $party['postal_code'], (string) $m->reference_value) >= 0 && strcmp((string) $party['postal_code'], (string) $m->range_end) <= 0, 'CITY' => $m->city_id !== null ? ($party['city_id'] ?? null) === $m->city_id : isset($party['city']) && $this->normalizer->normalize((string) $party['city']) === $this->normalizer->normalize((string) $m->reference_value), 'POLYGON' => $this->polygons->contains(json_decode($m->geometry, true, 512, JSON_THROW_ON_ERROR), (float) $party['latitude'], (float) $party['longitude']), 'PROVINCE' => $m->province_id !== null ? ($party['province_id'] ?? null) === $m->province_id : isset($party['state']) && $this->normalizer->normalize((string) $party['state']) === $this->normalizer->normalize((string) $m->reference_value), default => false }; if ($matchesMember) { $m->effective_precedence = $this->memberPrecedence((string) $m->member_type); $matches[] = $m; } }
        if ($matches === []) throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'Pricing zone could not be resolved.', details: ['reason_code' => 'PRICING_ZONE_UNRESOLVED']);
        usort($matches, fn ($left, $right) => $right->effective_precedence <=> $left->effective_precedence); $top = $matches[0]->effective_precedence; $winners = array_values(array_filter($matches, fn ($m) => $m->effective_precedence === $top));
        if (count(array_unique(array_map(fn ($m) => $m->pricing_zone_id, $winners))) > 1) throw new ApiException(ApiErrorCode::PricingZoneAmbiguous, 422, 'Pricing zone is ambiguous.', details: ['reason_code' => 'PRICING_ZONE_AMBIGUOUS']);
        $winner = $winners[0]; return [['pricing_zone_id' => $winner->pricing_zone_id, 'code' => $winner->code, 'title' => $winner->title, 'rank' => $winner->rank === null ? null : (int) $winner->rank, 'remote_area' => (bool) $winner->remote_area], ['member_id' => $winner->zone_member_id, 'member_type' => $winner->member_type, 'precedence' => $winner->effective_precedence]];
    }

    private function matrixCell(object $tariff, array $offering, array $input, array $origin, array $basisZone, float $weight): ?string
    {
        $matrices = json_decode($tariff->freight_matrices ?? '[]', true) ?? [];
        if ($matrices === []) return null;
        $codes = DB::table('pricing_zones')->where('zone_set_version_id', $tariff->zone_set_version_id)->pluck('code', 'pricing_zone_id')->all();
        $candidates = array_values(array_filter($matrices, static function ($m) use ($offering, $input, $origin, $codes): bool {
            return $m['service_offering_version_id'] === $offering['service_offering_version_id']
                && (empty($m['service_option_version_id']) || in_array($m['service_option_version_id'], $input['selected_option_version_ids'], true))
                && (empty($m['origin_zone_id']) || ($codes[$m['origin_zone_id']] ?? null) === $origin['code']);
        }));
        $specific = array_values(array_filter($candidates, static fn ($m) => ! empty($m['service_option_version_id'])));
        if ($specific !== []) $candidates = $specific;
        if ($candidates === []) return null;
        if (count($candidates) !== 1) throw new ApiException(ApiErrorCode::PricingRejected, 422, 'More than one freight matrix applies.', details: ['reason_code' => 'PRICING_MATRIX_AMBIGUOUS']);
        $bands = $candidates[0]['bands'];
        $bands = [...$bands, ...$this->matrices->linearBands($candidates[0])];
        foreach ($bands as $band) {
            if ($weight < (float) $band['from'] || ($band['to'] !== null && $weight >= (float) $band['to'])) continue;
            foreach ($band['cells'] as $cell) {
                if (($codes[$cell['zone_id']] ?? null) !== $basisZone['code']) continue;
                if ($cell['state'] === 'RATE') return $cell['id'];
                throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'The selected freight lane is not priced.', details: ['reason_code' => $cell['state'] === 'UNCOVERED' ? 'PRICING_LANE_UNCOVERED' : 'PRICING_MATRIX_RATE_MISSING']);
            }
        }
        throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'The shipment is outside the declared freight matrix.', details: ['reason_code' => 'PRICING_MATRIX_OUTSIDE_DOMAIN']);
    }

    private function resolveEffectiveZoneSetVersion(string $configuredVersionId, CarbonImmutable $asOf): string
    {
        $identityId = DB::table('pricing_zone_set_versions')
            ->where('zone_set_version_id', $configuredVersionId)
            ->value('pricing_zone_set_id');
        if ($identityId === null) {
            throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'Pricing Zone Set version could not be resolved.', details: ['reason_code' => 'PRICING_ZONE_VERSION_UNAVAILABLE']);
        }

        $versions = DB::table('pricing_zone_set_versions')
            ->where('pricing_zone_set_id', $identityId)
            ->where('status', 'PUBLISHED')
            ->where('valid_from', '<=', $asOf)
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>', $asOf))
            ->orderByDesc('version_number')
            ->pluck('zone_set_version_id');
        if ($versions->isEmpty()) {
            throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'No effective published Pricing Zone Set version was found.', details: ['reason_code' => 'PRICING_ZONE_VERSION_UNAVAILABLE']);
        }
        if ($versions->count() > 1) {
            throw new ApiException(ApiErrorCode::PricingZoneAmbiguous, 422, 'More than one effective Pricing Zone Set version was found.', details: ['reason_code' => 'PRICING_ZONE_VERSION_AMBIGUOUS']);
        }

        return (string) $versions->first();
    }

    /** @param array<string,mixed> $input @param array<string,mixed> $tariff @param array<string,mixed> $destination @return array<string,float|int|bool|string> */
    private function facts(array $input, array $tariff, array $destination): array
    {
        $parcels = array_values(array_filter((array) ($input['parcels'] ?? []), fn ($p) => isset($p['weight_kg']))); $actual = 0.0; $billable = 0.0; $step = (float) $tariff['weight_rounding_step_kg']; $divisor = (float) $tariff['volumetric_divisor'];
        if ($parcels !== []) { foreach ($parcels as $parcel) { $weight = (float) $parcel['weight_kg']; $volume = isset($parcel['length_cm'], $parcel['width_cm'], $parcel['height_cm']) ? ((float) $parcel['length_cm'] * (float) $parcel['width_cm'] * (float) $parcel['height_cm']) / $divisor : 0; $actual += $weight; $billable += $this->roundWeight(max($weight, $volume), $step, (string) $tariff['rounding_mode']); } $evidence = 'PER_PARCEL'; }
        else { if (! isset($input['weight_kg'])) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Parcel or aggregate weight is required.'); $actual = (float) $input['weight_kg']; $volume = isset($input['length_cm'], $input['width_cm'], $input['height_cm']) ? ((float) $input['length_cm'] * (float) $input['width_cm'] * (float) $input['height_cm']) / $divisor : 0; $billable = $this->roundWeight(max($actual, $volume), $step, (string) $tariff['rounding_mode']); $evidence = 'AGGREGATE_FALLBACK'; }
        return ['actual_weight_kg' => $actual, 'billable_weight_kg' => $billable, 'parcel_count' => max(1, count($parcels)), 'declared_value_amount' => (int) ($input['declared_value_amount'] ?? 0), 'cod_amount' => (int) ($input['cod_amount'] ?? 0), 'insurance_enabled' => (bool) ($input['insurance_enabled'] ?? false), 'cod_enabled' => (bool) ($input['cod_enabled'] ?? false), 'remote_area' => (bool) $destination['remote_area'], 'weight_evidence' => $evidence];
    }

    /** @param list<array<string,mixed>> $lines */
    private function insertLines(string $table, string $id, string $parent, string $parentId, array $lines): void
    {
        foreach ($lines as $index => $line) DB::table($table)->insert([$id => (string) Str::uuid(), $parent => $parentId, 'line_number' => $index + 1, 'charge_type_id' => $line['charge_type_id'], 'rate_rule_id' => $line['rate_rule_id'], 'charge_type_code' => $line['charge_code'], 'title' => $line['title'], 'calculation_method' => $line['calculation_method'], 'basis' => $line['basis'], 'quantity' => $line['quantity'], 'unit_rate' => $line['unit_rate'], 'amount' => $line['amount'], 'accounting_mapping_key' => $line['accounting_mapping_key'], 'explanation' => json_encode($line['explanation'], JSON_THROW_ON_ERROR)]);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function normalize(array $input): array
    {
        if (! (bool) ($input['insurance_enabled'] ?? false)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Declared-value insurance is mandatory.', fieldErrors: ['insurance_enabled' => ['Insurance is required.']]);
        $packages = $input['parcels'] ?? [];
        if ($packages === []) $packages = [$input];
        foreach ($packages as $index => $package) {
            if (! isset($package['weight_kg']) || ! is_numeric($package['weight_kg']) || ! is_finite((float) $package['weight_kg']) || $package['weight_kg'] <= 0) throw new ApiException(ApiErrorCode::ValidationError, 422, 'A positive parcel weight is required.', fieldErrors: ["parcels.{$index}.weight_kg" => ['Positive weight required.']]);
            $dimensions = array_filter(array_intersect_key($package, array_flip(['length_cm', 'width_cm', 'height_cm'])), fn ($v) => $v !== null);
            if ($dimensions !== [] && (count($dimensions) !== 3 || count(array_filter($dimensions, fn ($v) => is_numeric($v) && is_finite((float) $v) && $v > 0)) !== 3)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Provide all three positive parcel dimensions or omit them together.', fieldErrors: ["parcels.{$index}" => ['Incomplete or invalid dimensions.']]);
        }
        if (($input['cod_enabled'] ?? false) && (int) ($input['cod_amount'] ?? 0) <= 0) throw new ApiException(ApiErrorCode::ValidationError, 422, 'COD requires a positive goods collection amount.');
        unset($input['_hq_id'], $input['_node_id'], $input['_actor_user_id'], $input['_actor_session_id'], $input['_pricing_request_id']); foreach (['sender', 'receiver'] as $party) { $input[$party] = $this->geography->canonicalizeContact((array) ($input[$party] ?? []), false); unset($input[$party]['city_reference']); } $input['purpose'] = $input['purpose'] ?? 'SALES'; $input['channel'] = $input['channel'] ?? 'BRANCH'; $input['as_of_timestamp'] = CarbonImmutable::parse((string) ($input['as_of_timestamp'] ?? now()->toISOString()))->utc()->toISOString(); $input['acceptance_at'] = $input['acceptance_at'] ?? $input['as_of_timestamp']; $input['selected_option_version_ids'] = array_values((array) ($input['selected_option_version_ids'] ?? [])); return $input;
    }

    /**
     * @param array<string,mixed> $member
     * @return array{city_id:string,province_id:string}
     */
    private function canonicalCityMember(array $member): array
    {
        $cityId = (string) ($member['city_id'] ?? '');
        if ($cityId !== '') {
            $city = DB::table('cities')->where('city_id', $cityId)->where('is_active', true)->first(['city_id', 'province_id']);
        } else {
            $legacyName = trim((string) ($member['reference_value'] ?? ''));
            $matches = DB::table('cities')->where('normalized_name', $this->normalizer->normalize($legacyName))->where('is_active', true)->get(['city_id', 'province_id']);
            if ($matches->count() !== 1) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Legacy CITY member cannot be mapped unambiguously to canonical Geography.');
            $city = $matches->first();
        }
        if ($city === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone member city is inactive or invalid.');
        $canonicalProvinceId = (string) $city->province_id;
        $providedProvinceId = (string) ($member['province_id'] ?? '');
        if ($providedProvinceId !== '' && $providedProvinceId !== $canonicalProvinceId) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'Zone member province does not match the selected City.',
                fieldErrors: ['province_id' => ['Province must match the selected City.']],
            );
        }
        return ['city_id' => (string) $city->city_id, 'province_id' => $canonicalProvinceId];
    }

    private function memberPrecedence(string $type): int
    {
        return match ($type) { 'EXPLICIT_OVERRIDE' => 400, 'POSTAL_RANGE' => 300, 'POLYGON' => 250, 'CITY' => 200, 'PROVINCE' => 100, default => 0 };
    }

    private function databaseTimestamp(mixed $value): ?string
    {
        return $value === null || $value === ''
            ? null
            : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }

    /** @param array<string,mixed> $value */
    private function fingerprint(array $value): string { $sort = function (&$item) use (&$sort) { if (is_array($item)) { if (! array_is_list($item)) ksort($item); foreach ($item as &$child) $sort($child); } }; $sort($value); return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE)); }

    /** @param array<string,mixed> $version */
    private function hasVersionOverlap(string $table, string $parentId, array $version): bool
    {
        if (! $version['valid_from']) return false;
        $versionId = $table === 'tariff_versions' ? 'tariff_version_id' : 'zone_set_version_id';
        $query = DB::table($table)->where($parentId, $version[$parentId])->where($versionId, '!=', $version[$versionId])
            ->whereIn('status', ['APPROVED', 'PUBLISHED'])
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $version['valid_from']));
        if ($version['valid_to']) $query->where('valid_from', '<', $version['valid_to']);
        return $query->exists();
    }

    /** @param list<array<string,mixed>> $rules */
    private function hasAmbiguousRuleRanges(array $rules): bool
    {
        foreach ($rules as $leftIndex => $left) {
            foreach (array_slice($rules, $leftIndex + 1) as $right) {
                $selector = ['service_offering_version_id', 'service_option_version_id', 'origin_zone_id', 'destination_zone_id', 'charge_type_id', 'priority', 'basis'];
                if (collect($selector)->contains(fn ($field) => ($left[$field] ?? null) !== ($right[$field] ?? null))) continue;
                $leftFrom = $left['range_from'] === null ? -INF : (float) $left['range_from'];
                $leftTo = $left['range_to'] === null ? INF : (float) $left['range_to'];
                $rightFrom = $right['range_from'] === null ? -INF : (float) $right['range_from'];
                $rightTo = $right['range_to'] === null ? INF : (float) $right['range_to'];
                if (max($leftFrom, $rightFrom) < min($leftTo, $rightTo)) return true;
            }
        }
        return false;
    }

    private function roundWeight(float $weight, float $step, string $mode): float
    {
        $units = $weight / $step;
        $rounded = match ($mode) {
            'HALF_UP' => round($units, 0, PHP_ROUND_HALF_UP),
            'HALF_EVEN' => round($units, 0, PHP_ROUND_HALF_EVEN),
            'FLOOR' => floor($units),
            default => ceil($units),
        };
        return max($step, $rounded * $step);
    }

    /** @return array{string,string,string,string} */
    private function pricingMap(string $kind): array
    {
        return match ($kind) {
            'tariffs' => ['tariff_families', 'tariff_versions', 'tariff_family_id', 'tariff_version_id'],
            'zone-sets' => ['pricing_zone_sets', 'pricing_zone_set_versions', 'pricing_zone_set_id', 'zone_set_version_id'],
            default => throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'),
        };
    }

    /** @param array<string,mixed> $input */
    private function assertTariffReferences(AuthenticatedPrincipal $actor, array $input): void
    {
        $zoneSetVersionId = (string) ($input['zone_set_version_id'] ?? '');
        $visible = DB::table('pricing_zone_set_versions as v')
            ->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')
            ->where('v.zone_set_version_id', $zoneSetVersionId)
            ->where(fn ($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $actor->hqId))->exists();
        if (! $visible) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $zoneIds = DB::table('pricing_zones')->where('zone_set_version_id', $zoneSetVersionId)->pluck('pricing_zone_id')->map(fn ($id) => (string) $id)->all();
        foreach ((array) ($input['rules'] ?? []) as $rule) {
            foreach (['origin_zone_id', 'destination_zone_id'] as $field) {
                $zoneId = $rule[$field] ?? null;
                if ($zoneId !== null && $zoneId !== '' && ! in_array((string) $zoneId, $zoneIds, true)) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'A tariff rule references a zone outside the selected Zone Set version.', details: ['field' => "rules.{$field}"]);
                }
            }
        }
    }

    private function assertDraft(?object $row, int $expected): void { if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'); if ($row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only drafts are editable.'); if ((int) $row->lock_version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The draft changed since it was loaded.', details: ['current_version' => (int) $row->lock_version]); }

    private function assertAccess(AuthenticatedPrincipal $actor, string $permission, bool $runtime = false): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.'); $context = $this->authorization->resolve($actor); $allowedModules = $runtime ? ['Pricing', 'Consignment'] : ['Pricing']; if (! collect($context['module_entitlements'])->contains(fn ($e) => in_array($e['module_code'], $allowedModules, true) && $e['status'] === 'ENABLED')) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.'); if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decode(array $row): array { foreach (['freight_matrices', 'normalized_input', 'resolution_evidence', 'warnings', 'explanation', 'conditions', 'basis_charge_codes'] as $field) if (isset($row[$field]) && is_string($row[$field])) $row[$field] = json_decode($row[$field], true); return $row; }

    /** @param array<string,mixed> $after */
    private function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $correlationId, array $after): void { $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, after: $after, sourceClient: 'BRANCH_PANEL'); $this->outbox->write($actor->hqId, $type, $id, 'pricing.configuration.changed', $correlationId, ['action' => $action, 'target_type' => $type, 'target_id' => $id]); }
}
