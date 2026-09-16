<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\CanonicalGeographyResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolver;

final readonly class ServiceCatalogService implements ServiceEligibilityResolver
{
    private const RESOURCES = [
        'service-types' => ['service_types', 'service_type_versions', 'service_type_id', 'service_type_version_id'],
        'shipping-methods' => ['shipping_methods', 'shipping_method_versions', 'shipping_method_id', 'shipping_method_version_id'],
        'options' => ['service_options', 'service_option_versions', 'service_option_id', 'service_option_version_id'],
        'offerings' => ['service_offerings', 'service_offering_versions', 'service_offering_id', 'service_offering_version_id'],
    ];

    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private CommitmentScheduleService $commitments,
        private CanonicalGeographyResolver $geography,
    ) {}

    /** @param array<string, mixed> $filters */
    public function listIdentities(AuthenticatedPrincipal $actor, string $resource, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'service_catalog.view');
        [$identity, $versions, $identityId, $versionId] = $this->map($resource);
        $query = DB::table("{$identity} as i")
            ->where(fn ($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $actor->hqId))
            ->select(['i.*'])
            ->selectSub(DB::table("{$versions} as v")->select('v.status')
                ->whereColumn("v.{$identityId}", "i.{$identityId}")
                ->orderByDesc('v.version_number')->limit(1), 'latest_status')
            ->selectSub(DB::table("{$versions} as v")->select('v.version_number')
                ->whereColumn("v.{$identityId}", "i.{$identityId}")
                ->orderByDesc('v.version_number')->limit(1), 'latest_version_number')
            ->selectSub(DB::table("{$versions} as v")->select("v.{$versionId}")
                ->whereColumn("v.{$identityId}", "i.{$identityId}")
                ->orderByDesc('v.version_number')->limit(1), 'latest_version_id')
            ->selectSub(DB::table("{$versions} as v")->select('v.labels')
                ->whereColumn("v.{$identityId}", "i.{$identityId}")
                ->orderByDesc('v.version_number')->limit(1), 'labels');
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where('i.code', 'like', $search);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('i.status', $filters['status']);
        }

        $page = $query->orderBy('i.code')->paginate(
            perPage: min(100, max(1, (int) ($filters['page_size'] ?? 25))),
            page: max(1, (int) ($filters['page'] ?? 1)),
        );
        $page->setCollection($page->getCollection()->map(fn ($row) => $this->decode((array) $row)));

        return $page;
    }

    /** @param array<string, mixed> $filters */
    public function listPublishedVersions(AuthenticatedPrincipal $actor, string $resource, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'service_catalog.view');
        [$identity, $versions, $identityId, $versionId] = $this->map($resource);
        $includeVersionIds = array_values(array_unique(array_map('strval', (array) ($filters['include_version_ids'] ?? []))));
        $includeVersionIds = array_map(fn ($id) => DB::table($versions)->where($identityId, $id)->orderByDesc('version_number')->value($versionId) ?? $id, $includeVersionIds);
        $query = DB::table("{$versions} as v")
            ->join("{$identity} as i", "i.{$identityId}", '=', "v.{$identityId}")
            ->where(fn ($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $actor->hqId));
        $search = ($filters['search'] ?? '') === '' ? null : '%'.addcslashes((string) $filters['search'], '%_\\').'%';
        $query->where(function ($available) use ($includeVersionIds, $search, $versionId): void {
            $available->where(function ($published) use ($search): void {
                $published->where('v.status', 'PUBLISHED')->where('i.status', 'ACTIVE');
                if ($search !== null) $published->where(fn ($match) => $match->where('i.code', 'like', $search)->orWhere('v.labels', 'like', $search));
            });
            if ($includeVersionIds !== []) $available->orWhereIn("v.{$versionId}", $includeVersionIds);
        });
        $page = $query->select(['v.*', 'i.code', 'i.status as identity_status'])
            ->orderBy('i.code')
            ->orderByDesc('v.version_number')
            ->paginate(
                perPage: min(100, max(1, (int) ($filters['page_size'] ?? 100))),
                page: max(1, (int) ($filters['page'] ?? 1)),
            );
        $page->setCollection($page->getCollection()->map(fn ($row) => $this->decode((array) $row)));

        return $page;
    }

    /** @param array<string,mixed> $filters */
    public function auditEvents(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'service_catalog.audit.view');
        $query = DB::table('audit_events')->where('hq_id', $actor->hqId)->where('action_key', 'like', 'SERVICE_CATALOG_%');
        if (! empty($filters['target_id'])) $query->where('target_id', $filters['target_id']);
        return $query->orderByDesc('created_at')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function createIdentity(AuthenticatedPrincipal $actor, string $resource, array $input, string $correlationId): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        if ($resource === 'offerings' && array_key_exists('availability_bindings', $input)) {
            $this->assertAccess($actor, 'service_catalog.availability.manage');
        }
        [$identity, $versions, $identityId, $versionId] = $this->map($resource);

        return $this->transactions->run(function () use ($actor, $resource, $input, $correlationId, $identity, $versions, $identityId, $versionId): array {
            $id = (string) Str::uuid();
            $draftId = (string) Str::uuid();
            $now = now();
            DB::table($identity)->insert([
                $identityId => $id,
                'hq_id' => $actor->hqId,
                'owner_key' => (string) $actor->hqId,
                'code' => !empty($input['code']) ? Str::upper((string) $input['code']) : CatalogCode::generate($identity, (string) $actor->hqId),
                'status' => 'ACTIVE',
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $version = $this->versionColumns($resource, $input) + [
                $versionId => $draftId,
                $identityId => $id,
                'hq_id' => $actor->hqId,
                'version_number' => 1,
                'previous_version_id' => null,
                'status' => 'DRAFT',
                'lock_version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            DB::table($versions)->insert($version);
            if ($resource === 'offerings') {
                $this->replaceOfferingChildren($draftId, $input, $actor->hqId);
            }
            $this->record($actor, 'SERVICE_CATALOG_IDENTITY_CREATED', Str::upper(str_replace('-', '_', $resource)), $id, $correlationId, ['version_id' => $draftId]);

            return $this->versionDetail($actor, $resource, $draftId);
        });
    }

    /** @return array<string, mixed> */
    public function cloneDraft(AuthenticatedPrincipal $actor, string $resource, string $identityIdValue, string $correlationId): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        [$identity, $versions, $identityId, $versionId] = $this->map($resource);

        return $this->transactions->run(function () use ($actor, $resource, $identityIdValue, $correlationId, $identity, $versions, $identityId, $versionId): array {
            $this->visibleIdentity($actor, $identity, $identityId, $identityIdValue);
            $previous = (array) DB::table($versions)->where($identityId, $identityIdValue)->orderByDesc('version_number')->lockForUpdate()->first();
            if ($previous === []) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if (DB::table($versions)->where($identityId, $identityIdValue)->whereIn('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED'])->exists()) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'An unpublished successor already exists.');
            }
            $newId = (string) Str::uuid();
            $previousId = (string) $previous[$versionId];
            unset($previous[$versionId], $previous['approved_by'], $previous['published_by'], $previous['approved_at'], $previous['published_at'], $previous['content_digest']);
            $previous[$versionId] = $newId;
            $previous['previous_version_id'] = $previousId;
            $previous['version_number'] = ((int) $previous['version_number']) + 1;
            $previous['status'] = 'DRAFT';
            $previous['lock_version'] = 1;
            $previous['valid_from'] = null;
            $previous['valid_to'] = null;
            $previous['created_by'] = $actor->userId;
            $previous['created_at'] = now();
            $previous['updated_at'] = now();
            DB::table($versions)->insert($previous);
            if ($resource === 'offerings') {
                $this->cloneOfferingChildren((string) $previous['previous_version_id'], $newId);
            }
            $this->record($actor, 'SERVICE_CATALOG_DRAFT_CLONED', 'SERVICE_CATALOG_VERSION', $newId, $correlationId, ['previous_version_id' => $previous['previous_version_id']]);

            return $this->versionDetail($actor, $resource, $newId);
        });
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateDraft(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue, int $expectedVersion, array $input, string $correlationId): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        if ($resource === 'offerings' && array_key_exists('availability_bindings', $input)) {
            $this->assertAccess($actor, 'service_catalog.availability.manage');
        }
        [, $versions, , $versionId] = $this->map($resource);

        return $this->transactions->run(function () use ($actor, $resource, $versionIdValue, $expectedVersion, $input, $correlationId, $versions, $versionId): array {
            $row = DB::table($versions)->where($versionId, $versionIdValue)->where('hq_id', $actor->hqId)->lockForUpdate()->first();
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ((string) $row->status !== 'DRAFT') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only draft versions can be edited.');
            }
            if ((int) $row->lock_version !== $expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The draft changed since it was loaded.', details: ['current_version' => (int) $row->lock_version]);
            }
            DB::table($versions)->where($versionId, $versionIdValue)->update($this->versionColumns($resource, $input) + [
                'lock_version' => $expectedVersion + 1,
                'updated_at' => now(),
            ]);
            if ($resource === 'offerings') {
                $this->replaceOfferingChildren($versionIdValue, $input, $actor->hqId);
            }
            $this->record($actor, 'SERVICE_CATALOG_DRAFT_UPDATED', 'SERVICE_CATALOG_VERSION', $versionIdValue, $correlationId, ['lock_version' => $expectedVersion + 1]);

            return $this->versionDetail($actor, $resource, $versionIdValue);
        });
    }

    /** @return array<string, mixed> */
    public function validateDraft(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue, bool $automatic = false): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        $row = $this->versionDetail($actor, $resource, $versionIdValue);
        $errors = [];
        if (empty($row['labels']) || ! is_array($row['labels'])) {
            $errors[] = ['code' => 'SERVICE_LABEL_REQUIRED', 'field' => 'labels'];
        }
        if ($row['valid_to'] && $row['valid_from'] && $row['valid_to'] <= $row['valid_from']) {
            $errors[] = ['code' => 'SERVICE_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        }
        if ($resource === 'offerings') {
            foreach (['service_type_version_id', 'shipping_method_version_id'] as $field) {
                $table = $field === 'service_type_version_id' ? 'service_type_versions' : 'shipping_method_versions';
                if (! DB::table($table)->where($field, $row[$field])->where('status', 'PUBLISHED')->exists()) {
                    $errors[] = ['code' => 'SERVICE_DEPENDENCY_NOT_PUBLISHED', 'field' => $field];
                }
            }
            if ($row['availability_bindings'] === []) {
                $errors[] = ['code' => 'SERVICE_AVAILABILITY_REQUIRED', 'field' => 'availability_bindings'];
            }
            foreach ($row['option_rules'] as $rule) {
                if (! DB::table('service_option_versions')->where('service_option_version_id', $rule['service_option_version_id'])->where('status', 'PUBLISHED')->exists()) {
                    $errors[] = ['code' => 'SERVICE_OPTION_VERSION_NOT_PUBLISHED', 'field' => 'option_rules'];
                }
                if ($rule['compatibility'] === 'CONDITIONAL' && empty($rule['condition'])) {
                    $errors[] = ['code' => 'SERVICE_OPTION_CONDITION_REQUIRED', 'field' => 'option_rules'];
                }
            }
            $binding = $row['commitment_binding'] ?? null;
            if ($binding !== null) {
                if (! DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id', $binding['commitment_schedule_version_id'])->where('hq_id', $actor->hqId)->where('status', 'PUBLISHED')->exists()) {
                    $errors[] = ['code' => 'COMMITMENT_SCHEDULE_VERSION_NOT_PUBLISHED', 'field' => 'commitment_binding.commitment_schedule_version_id'];
                }
                if ($binding['delivery_mode'] === 'COMPUTED' && (empty($binding['duration_value']) || empty($binding['duration_unit']) || empty($binding['duration_anchor']))) {
                    $errors[] = ['code' => 'COMPUTED_DELIVERY_CONFIGURATION_REQUIRED', 'field' => 'commitment_binding'];
                }
                if ($binding['delivery_mode'] === 'COMPUTED' && $binding['pickup_mode'] === 'NONE' && in_array($binding['duration_anchor'], ['PICKUP_COMMITMENT_START', 'PICKUP_COMMITMENT_END'], true)) {
                    $errors[] = ['code' => 'COMPUTED_DELIVERY_ANCHOR_UNAVAILABLE', 'field' => 'commitment_binding.duration_anchor'];
                }
            }
        }
        $overlap = $this->hasEffectiveOverlap($resource, $row);
        if ($overlap && !$automatic) {
            $errors[] = ['code' => 'SERVICE_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /** @return array<string, mixed> */
    public function transition(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue, string $action, string $correlationId): array
    {
        $permission = match ($action) {
            'approve' => 'service_catalog.approve',
            'publish' => 'service_catalog.publish',
            default => 'service_catalog.manage_draft',
        };
        $this->assertAccess($actor, $permission);
        [, $versions, , $versionId] = $this->map($resource);

        return $this->transactions->run(function () use ($actor, $resource, $versionIdValue, $action, $correlationId, $versions, $versionId): array {
            $row = DB::table($versions)->where($versionId, $versionIdValue)->where('hq_id', $actor->hqId)->lockForUpdate()->first();
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $changes = match ($action) {
                'approve' => $this->approvalChanges($actor, $row, $resource, $versionIdValue),
                'publish' => $this->publicationChanges($actor, $row, $resource, $versionIdValue),
                'supersede' => $this->simpleTransition($row, 'PUBLISHED', 'SUPERSEDED'),
                'archive' => $this->simpleTransition($row, 'SUPERSEDED', 'ARCHIVED'),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unsupported lifecycle action.'),
            };
            DB::table($versions)->where($versionId, $versionIdValue)->update($changes + ['updated_at' => now()]);
            $event = 'SERVICE_CATALOG_VERSION_'.Str::upper($action).'D';
            $this->record($actor, $event, 'SERVICE_CATALOG_VERSION', $versionIdValue, $correlationId, ['status' => $changes['status']]);

            return $this->versionDetail($actor, $resource, $versionIdValue);
        });
    }

    /** @return list<array<string, mixed>> */
    public function history(AuthenticatedPrincipal $actor, string $resource, string $identityIdValue): array
    {
        $this->assertAccess($actor, 'service_catalog.history.view');
        [$identity, $versions, $identityId, $versionId] = $this->map($resource);
        $this->visibleIdentity($actor, $identity, $identityId, $identityIdValue);

        return DB::table($versions)->where($identityId, $identityIdValue)->orderByDesc('version_number')->pluck($versionId)->map(fn ($id) => $this->versionDetail($actor, $resource, (string) $id))->all();
    }

    /** @param array<string, mixed> $context @return list<array<string, mixed>> */
    public function resolve(AuthenticatedPrincipal $actor, array $context): array
    {
        $this->assertAccess($actor, 'service_catalog.resolve', runtime: true);
        $context = $this->canonicalizeCoverageContext($context);
        $context['schedule_node_ids']=$this->authorization->resolve($actor)['accessible_node_ids']??[];
        $asOf = CarbonImmutable::parse((string) ($context['as_of_timestamp'] ?? now()->toISOString()))->utc();
        $channel = (string) ($context['channel'] ?? 'BRANCH');
        $rows = DB::table('service_offering_versions as v')
            ->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')
            ->join('service_type_versions as stv', 'stv.service_type_version_id', '=', 'v.service_type_version_id')
            ->join('shipping_method_versions as smv', 'smv.shipping_method_version_id', '=', 'v.shipping_method_version_id')
            ->where('v.status', 'PUBLISHED')->where('i.status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('v.hq_id')->orWhere('v.hq_id', $actor->hqId))
            ->select(['v.*', 'i.code as offering_code', 'stv.service_type_id', 'smv.shipping_method_id'])
            ->orderBy('i.code')->limit(100)->get();
        $results = [];
        foreach ($rows as $row) {
            if (! $this->available((string) $row->service_offering_version_id, (string) $actor->hqId, $channel)) {
                continue;
            }
            try { $row = (object) $this->runtimeDependencies((array) $row, (string) $actor->hqId); }
            catch (ApiException $error) { if (($error->details['reason_code'] ?? '') === 'CATALOG_DEPENDENCY_UNAVAILABLE') continue; throw $error; }
            $decision = $this->evaluate((array) $row, $context);
            if ($decision['outcome'] !== 'INELIGIBLE') {
                try {
                    $commitment = $this->commitments->resolveForOffering((string) $row->service_offering_version_id, $context, false);
                } catch (ApiException $exception) {
                    $reasonCode = $exception->details['reason_code'] ?? null;
                    if (in_array($reasonCode, ['PICKUP_WINDOW_INVALID', 'DELIVERY_WINDOW_INVALID', 'CATALOG_DEPENDENCY_UNAVAILABLE', 'COMMITMENT_SCOPE_UNAVAILABLE', 'SLA_CALENDAR_UNAVAILABLE'], true)) {
                        continue;
                    }

                    throw $exception;
                }
                if ($commitment !== null && $commitment['eligible'] !== true) continue;
                $results[] = [...$this->decode((array) $row), ...$decision, 'options' => $this->resolvedOptions((string) $row->service_offering_version_id, $context), 'commitment' => $commitment ?? $this->commitment((array) $row, $context)];
            }
        }

        return $results;
    }

    public function validateSelection(AuthenticatedPrincipal $actor, string $offeringId, ?string $versionId, array $context, bool $requireCommitmentSelection = true): array
    {
        $this->assertAccess($actor, 'service_catalog.resolve', runtime: true);
        $context = $this->canonicalizeCoverageContext($context);
        $context['schedule_node_ids']=$this->authorization->resolve($actor)['accessible_node_ids']??[];
        $query = DB::table('service_offering_versions as v')->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')
            ->join('service_type_versions as stv', 'stv.service_type_version_id', '=', 'v.service_type_version_id')
            ->join('shipping_method_versions as smv', 'smv.shipping_method_version_id', '=', 'v.shipping_method_version_id')
            ->where('i.service_offering_id', $offeringId)->where('i.status', 'ACTIVE')->where('v.status', 'PUBLISHED')
            ->where(fn ($q) => $q->whereNull('v.hq_id')->orWhere('v.hq_id', $actor->hqId));
        if ($versionId) {
            if (!in_array($versionId, CurrentCatalog::relatedVersions('offerings', $offeringId), true))
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected service reference is invalid.');
            $query->orderByDesc('v.version_number');
        } else {
            $query->orderByDesc('v.version_number');
        }
        $asOf = CarbonImmutable::parse((string) ($context['as_of_timestamp'] ?? now()->toISOString()))->utc();
        $row = $query->select(['v.*', 'i.code as offering_code', 'stv.service_type_id', 'smv.shipping_method_id'])->first();
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'No effective published Service Offering version exists.', details: ['reason_code' => 'SERVICE_VERSION_NOT_EFFECTIVE']);
        }
        $row = (object) $this->runtimeDependencies((array) $row, (string) $actor->hqId);
        if (!$this->available((string) $row->service_offering_version_id, (string) $actor->hqId, (string) ($context['channel'] ?? 'BRANCH')))
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The service is unavailable for this channel.', details: ['reason_code' => 'SERVICE_UNAVAILABLE']);
        $decision = $this->evaluate((array) $row, $context);
        if ($decision['outcome'] !== 'ELIGIBLE') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected Service Offering is not eligible.', details: $decision);
        }

        $commitment = $this->commitments->resolveForOffering((string) $row->service_offering_version_id, $context, $requireCommitmentSelection);
        if ($commitment !== null && $commitment['eligible'] !== true) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected Pickup commitment is not eligible.', details: ['reason_code' => $commitment['reason_code']]);
        }
        return [...$this->decode((array) $row), ...$decision, 'options' => $this->resolvedOptions((string) $row->service_offering_version_id, $context), 'commitment' => $commitment ?? $this->commitment((array) $row, $context)];
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    public function commitmentPreview(AuthenticatedPrincipal $actor, string $offeringId, array $context): array
    {
        $selection = $this->validateSelection($actor, $offeringId, $context['service_offering_version_id'] ?? null, $context, false);

        return (array) $selection['commitment'];
    }

    /** @return array{string,string,string,string} */
    private function map(string $resource): array
    {
        return self::RESOURCES[$resource] ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function versionColumns(string $resource, array $input): array
    {
        $base = [
            'labels' => json_encode($input['labels'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'description' => $input['description'] ?? null,
            'valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null),
            'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null),
        ];
        if ($resource !== 'offerings') {
            return $base + ['definition' => json_encode($input['definition'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
        }

        return $base + [
            'service_type_version_id' => $input['service_type_version_id'],
            'shipping_method_version_id' => $input['shipping_method_version_id'],
            'sla_policy' => json_encode($input['sla_policy'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'availability_summary' => json_encode($input['availability_summary'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    private function databaseTimestamp(mixed $value): ?string
    {
        return $value === null || $value === ''
            ? null
            : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }

    /** @param array<string, mixed> $input */
    private function replaceOfferingChildren(string $versionId, array $input, ?string $hqId): void
    {
        $this->assertOfferingReferences($input, $hqId);
        foreach (['service_offering_option_rules', 'service_eligibility_rules', 'service_coverage_references', 'service_availability_bindings', 'service_offering_commitment_bindings'] as $table) {
            DB::table($table)->where('service_offering_version_id', $versionId)->delete();
        }
        foreach ((array) ($input['option_rules'] ?? []) as $rule) {
            DB::table('service_offering_option_rules')->insert([
                'offering_option_rule_id' => (string) Str::uuid(), 'service_offering_version_id' => $versionId,
                'service_option_version_id' => $rule['service_option_version_id'], 'compatibility' => $rule['compatibility'],
                'condition' => isset($rule['condition']) ? json_encode($rule['condition'], JSON_THROW_ON_ERROR) : null,
            ]);
        }
        foreach ((array) ($input['eligibility_rules'] ?? []) as $rule) {
            DB::table('service_eligibility_rules')->insert([
                'eligibility_rule_id' => (string) Str::uuid(), 'service_offering_version_id' => $versionId,
                'dimension' => $rule['dimension'], 'fact_key' => $rule['fact_key'], 'operator' => $rule['operator'],
                'expected_value' => json_encode($rule['expected_value'], JSON_THROW_ON_ERROR), 'reason_code' => $rule['reason_code'],
                'priority' => $rule['priority'] ?? 100,
            ]);
        }
        foreach ((array) ($input['coverage_references'] ?? []) as $reference) {
            DB::table('service_coverage_references')->insert([
                'coverage_reference_id' => (string) Str::uuid(), 'service_offering_version_id' => $versionId,
                'direction' => $reference['direction'], 'reference_type' => $reference['reference_type'],
                'reference_value' => $reference['reference_value'],
                'secondary_reference_value' => $reference['reference_type'] === 'POSTAL_RANGE' ? ($reference['secondary_reference_value'] ?? null) : null,
                'priority' => $reference['priority'] ?? 100,
            ]);
        }
        foreach ((array) ($input['availability_bindings'] ?? []) as $binding) {
            $scopeValue = match ($binding['scope_type']) {
                'TENANT' => $hqId,
                'PLATFORM' => null,
                default => $binding['scope_value'] ?? null,
            };
            DB::table('service_availability_bindings')->insert([
                'availability_binding_id' => (string) Str::uuid(), 'service_offering_version_id' => $versionId,
                'scope_type' => $binding['scope_type'], 'scope_value' => $scopeValue,
                'enabled' => $binding['enabled'] ?? true,
            ]);
        }
        if (isset($input['commitment_binding']) && is_array($input['commitment_binding'])) {
            $binding = $input['commitment_binding'];
            DB::table('service_offering_commitment_bindings')->insert([
                'offering_commitment_binding_id' => (string) Str::uuid(),
                'service_offering_version_id' => $versionId,
                'commitment_schedule_version_id' => $binding['commitment_schedule_version_id'],
                'pickup_mode' => $binding['pickup_mode'], 'delivery_mode' => $binding['delivery_mode'],
                'duration_value' => $binding['delivery_mode'] === 'COMPUTED' ? ($binding['duration_value'] ?? null) : null,
                'duration_unit' => $binding['delivery_mode'] === 'COMPUTED' ? ($binding['duration_unit'] ?? null) : null,
                'duration_anchor' => $binding['delivery_mode'] === 'COMPUTED' ? ($binding['duration_anchor'] ?? null) : null,
            ]);
        }
    }

    /** @param array<string,mixed> $input */
    private function assertOfferingReferences(array $input, ?string $hqId): void
    {
        foreach ((array) ($input['availability_bindings'] ?? []) as $binding) {
            $scopeType = (string) ($binding['scope_type'] ?? '');
            if (! in_array($scopeType, ['PLATFORM', 'TENANT', 'CHANNEL'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected availability scope has no authoritative reference directory.');
            }
            if ($scopeType === 'CHANNEL' && ! in_array((string) ($binding['scope_value'] ?? ''), ['BRANCH', 'VENDOR', 'DRIVER', 'HQ', 'API', 'TRACKING', 'LEGACY'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The availability channel is invalid.');
            }
        }
        foreach ((array) ($input['coverage_references'] ?? []) as $reference) {
            $type = (string) ($reference['reference_type'] ?? '');
            $value = (string) ($reference['reference_value'] ?? '');
            $valid = match ($type) {
                'COUNTRY' => $value === 'IR',
                'PROVINCE' => DB::table('provinces')->where(['province_id' => $value, 'is_active' => true])->exists(),
                'CITY' => DB::table('cities')->where(['city_id' => $value, 'is_active' => true])->exists(),
                'OPERATIONAL_AREA' => $hqId !== null && DB::table('areas')->where(['area_id' => $value, 'hq_id' => $hqId])->exists(),
                'PRICING_ZONE_SET' => DB::table('pricing_zone_set_versions as v')->join('pricing_zone_sets as s', 's.pricing_zone_set_id', '=', 'v.pricing_zone_set_id')->where('v.zone_set_version_id', $value)->where(fn ($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $hqId))->exists(),
                'POSTAL_RANGE' => preg_match('/^\d{10}$/', $value) === 1 && preg_match('/^\d{10}$/', (string) ($reference['secondary_reference_value'] ?? '')) === 1 && strcmp($value, (string) $reference['secondary_reference_value']) <= 0,
                default => false,
            };
            if (! $valid) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The coverage reference is invalid or outside the current tenant.');
        }
    }

    private function cloneOfferingChildren(string $from, string $to): void
    {
        $specs = [
            'service_offering_option_rules' => 'offering_option_rule_id',
            'service_eligibility_rules' => 'eligibility_rule_id',
            'service_coverage_references' => 'coverage_reference_id',
            'service_availability_bindings' => 'availability_binding_id',
            'service_offering_commitment_bindings' => 'offering_commitment_binding_id',
        ];
        foreach ($specs as $table => $primary) {
            foreach (DB::table($table)->where('service_offering_version_id', $from)->get() as $row) {
                $copy = (array) $row; $copy[$primary] = (string) Str::uuid(); $copy['service_offering_version_id'] = $to;
                DB::table($table)->insert($copy);
            }
        }
    }

    /** @return array<string, mixed> */
    public function versionDetail(AuthenticatedPrincipal $actor, string $resource, string $versionIdValue): array
    {
        [$identity, $versions, $identityId, $versionId] = $this->map($resource);
        $row = DB::table("{$versions} as v")->join("{$identity} as i", "i.{$identityId}", '=', "v.{$identityId}")
            ->where("v.{$versionId}", $versionIdValue)->where(fn ($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $actor->hqId))
            ->select(['v.*', 'i.code'])->first();
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $result = $this->decode((array) $row);
        if ($resource === 'offerings') {
            $result['option_rules'] = $this->decodedRows('service_offering_option_rules', $versionIdValue);
            $result['eligibility_rules'] = $this->decodedRows('service_eligibility_rules', $versionIdValue);
            $result['coverage_references'] = $this->decodedRows('service_coverage_references', $versionIdValue);
            $result['availability_bindings'] = $this->decodedRows('service_availability_bindings', $versionIdValue);
            $binding = DB::table('service_offering_commitment_bindings')->where('service_offering_version_id', $versionIdValue)->first();
            $result['commitment_binding'] = $binding === null ? null : (array) $binding;
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function decodedRows(string $table, string $versionId): array
    {
        return DB::table($table)->where('service_offering_version_id', $versionId)->get()->map(fn ($row) => $this->decode((array) $row))->all();
    }

    /** @param array<string, mixed> $row */
    private function hasEffectiveOverlap(string $resource, array $row): bool
    {
        [, $versions, $identityId, $versionId] = $this->map($resource);
        $query = DB::table($versions)->where($identityId, $row[$identityId])->where($versionId, '!=', $row[$versionId])
            ->whereIn('status', ['PUBLISHED', 'APPROVED']);
        if ($row['valid_to']) {
            $query->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<', $row['valid_to']));
        }
        if ($row['valid_from']) {
            $query->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $row['valid_from']));
        }

        return $query->exists();
    }

    /** @return array<string, mixed> */
    private function approvalChanges(AuthenticatedPrincipal $actor, object $row, string $resource, string $versionId): array
    {
        if (! in_array((string) $row->status, ['DRAFT', 'READY_FOR_APPROVAL'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a validated draft can be approved.');
        }
        $validation = $this->validateDraft($actor, $resource, $versionId);
        if (! $validation['valid']) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Catalog validation failed.', details: $validation);
        }
        return ['status' => 'APPROVED', 'approved_by' => $actor->userId, 'approved_at' => now()];
    }

    /** @return array<string, mixed> */
    private function publicationChanges(AuthenticatedPrincipal $actor, object $row, string $resource, string $versionId): array
    {
        if ((string) $row->status !== 'APPROVED') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.');
        }
        $detail = $this->versionDetail($actor, $resource, $versionId);
        return ['status' => 'PUBLISHED', 'published_by' => $actor->userId, 'published_at' => now(), 'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
    }

    /** @return array{status:string} */
    private function simpleTransition(object $row, string $from, string $to): array
    {
        if ((string) $row->status !== $from) throw new ApiException(ApiErrorCode::ValidationError, 422, "Only {$from} can transition to {$to}.");
        return ['status' => $to];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $context @return array<string, mixed> */
    private function evaluate(array $row, array $context): array
    {
        $reasons = []; $missing = [];
        $coverage = $this->decodedRows('service_coverage_references', (string) $row['service_offering_version_id']);
        foreach (['ORIGIN' => 'sender', 'DESTINATION' => 'receiver'] as $direction => $party) {
            $references = array_values(array_filter($coverage, fn ($reference) => in_array($reference['direction'], [$direction, 'BOTH'], true)));
            if ($references !== [] && ! collect($references)->contains(fn ($reference) => $this->coverageMatches($reference, (array) ($context[$party] ?? [])))) {
                $reasons[] = 'SERVICE_COVERAGE_UNSUPPORTED';
            }
        }
        foreach ($this->decodedRows('service_eligibility_rules', (string) $row['service_offering_version_id']) as $rule) {
            $actual = data_get($context, (string) $rule['fact_key']);
            $expected = $rule['expected_value'];
            if ($actual === null) { $missing[] = $rule['fact_key']; continue; }
            $passes = match ($rule['operator']) {
                'EQ' => $actual == $expected, 'NEQ' => $actual != $expected,
                'IN' => in_array($actual, (array) $expected, true), 'NOT_IN' => ! in_array($actual, (array) $expected, true),
                'MIN' => (float) $actual >= (float) $expected, 'MAX' => (float) $actual <= (float) $expected,
                'BETWEEN' => (float) $actual >= (float) ($expected[0] ?? 0) && (float) $actual <= (float) ($expected[1] ?? 0),
                'EXISTS' => $actual !== null, 'NOT_EXISTS' => $actual === null, default => false,
            };
            if (! $passes) $reasons[] = $rule['reason_code'];
        }
        $selected = array_map(fn ($id) => CurrentCatalog::resolve('options', (string) $id, $row['hq_id'])['service_option_id'], array_values((array) ($context['selected_option_version_ids'] ?? [])));
        $optionRules = $this->decodedRows('service_offering_option_rules', (string) $row['service_offering_version_id']);
        foreach ($optionRules as &$rule) {
            $rule['service_option_version_id'] = DB::table('service_option_versions')->where('service_option_version_id', $rule['service_option_version_id'])->value('service_option_id');
        }
        unset($rule);
        $knownOptionVersions = array_column($optionRules, 'service_option_version_id');
        if (array_diff($selected, $knownOptionVersions) !== []) $reasons[] = 'SERVICE_OPTION_NOT_ALLOWED';
        foreach ($optionRules as $rule) {
            $has = in_array($rule['service_option_version_id'], $selected, true);
            if ($rule['compatibility'] === 'REQUIRED' && ! $has) $reasons[] = 'SERVICE_OPTION_REQUIRED';
            if ($rule['compatibility'] === 'FORBIDDEN' && $has) $reasons[] = 'SERVICE_OPTION_FORBIDDEN';
            if ($rule['compatibility'] === 'CONDITIONAL' && $has && ! $this->conditionPasses((array) $rule['condition'], $context)) $reasons[] = 'SERVICE_OPTION_CONDITION_NOT_MET';
        }
        $outcome = $reasons !== [] ? 'INELIGIBLE' : ($missing !== [] ? 'UNKNOWN' : 'ELIGIBLE');
        return ['outcome' => $outcome, 'reason_codes' => array_values(array_unique($reasons)), 'missing_facts' => array_values(array_unique($missing)), 'evidence' => ['evaluated_at' => now()->toISOString()]];
    }

    /** @param array<string,mixed> $reference @param array<string,mixed> $party */
    private function coverageMatches(array $reference, array $party): bool
    {
        return match ($reference['reference_type']) {
            'CITY' => ($party['city_id'] ?? null) === $reference['reference_value'] || (isset($party['city']) && mb_strtolower((string) $party['city']) === mb_strtolower((string) $reference['reference_value'])),
            'PROVINCE' => ($party['province_id'] ?? null) === $reference['reference_value'] || (isset($party['state']) && mb_strtolower((string) $party['state']) === mb_strtolower((string) $reference['reference_value'])),
            'COUNTRY' => isset($party['country']) && mb_strtolower((string) $party['country']) === mb_strtolower((string) $reference['reference_value']),
            'POSTAL_RANGE' => isset($party['postal_code']) && strcmp((string) $party['postal_code'], (string) $reference['reference_value']) >= 0 && strcmp((string) $party['postal_code'], (string) $reference['secondary_reference_value']) <= 0,
            default => false,
        };
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function canonicalizeCoverageContext(array $context): array
    {
        foreach (['sender', 'receiver'] as $party) {
            $contact = $this->geography->canonicalizeContact((array) ($context[$party] ?? []), false);
            $contact['country'] ??= 'IR';
            $context[$party] = $contact;
        }

        return $context;
    }

    /** @param array<string,mixed> $condition @param array<string,mixed> $context */
    private function conditionPasses(array $condition, array $context): bool
    {
        $actual = data_get($context, (string) ($condition['fact_key'] ?? ''));
        $expected = $condition['expected_value'] ?? null;

        return match ($condition['operator'] ?? 'EQ') {
            'EQ' => $actual == $expected,
            'NEQ' => $actual != $expected,
            'IN' => in_array($actual, (array) $expected, true),
            'NOT_IN' => ! in_array($actual, (array) $expected, true),
            'MIN' => $actual !== null && (float) $actual >= (float) $expected,
            'MAX' => $actual !== null && (float) $actual <= (float) $expected,
            'BETWEEN' => $actual !== null && (float) $actual >= (float) ($expected[0] ?? 0) && (float) $actual <= (float) ($expected[1] ?? 0),
            'EXISTS' => $actual !== null,
            'NOT_EXISTS' => $actual === null,
            default => false,
        };
    }

    /** @param array<string,mixed> $context @return list<array<string,mixed>> */
    private function resolvedOptions(string $offeringVersionId, array $context): array
    {
        $results = [];
        foreach ($this->decodedRows('service_offering_option_rules', $offeringVersionId) as $rule) {
            $owner = DB::table('service_offering_versions')->where('service_offering_version_id', $offeringVersionId)->value('hq_id');
            try { $current = CurrentCatalog::resolve('options', (string) $rule['service_option_version_id'], $owner); }
            catch (ApiException $error) { if ($rule['compatibility'] === 'REQUIRED') throw $error; continue; }
            $conditionMet = $rule['compatibility'] !== 'CONDITIONAL' || $this->conditionPasses((array) $rule['condition'], $context);
            $results[] = ['service_option_id' => $current['service_option_id'], 'service_option_version_id' => $current['service_option_version_id'], 'code' => $current['code'], 'labels' => json_decode($current['labels'], true), 'definition' => json_decode($current['definition'], true), 'compatibility' => $rule['compatibility'], 'required' => $rule['compatibility'] === 'REQUIRED', 'selectable' => $rule['compatibility'] !== 'FORBIDDEN' && $conditionMet, 'reason_code' => $conditionMet ? null : 'SERVICE_OPTION_CONDITION_NOT_MET'];
        }
        return $results;
    }

    private function runtimeDependencies(array $row, string $hqId): array
    {
        foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $resource) {
            $dependency = CurrentCatalog::resolve($resource, (string) $row[$field], $hqId);
            $row[$field] = $dependency[$field];
            $row[str_replace('_version_id', '_labels', $field)] = json_decode($dependency['labels'], true);
        }
        return $row;
    }

    private function available(string $versionId, string $hqId, string $channel): bool
    {
        $bindings = DB::table('service_availability_bindings')->where('service_offering_version_id', $versionId)->where('enabled', true)->get();
        return $bindings->contains(fn ($b) => $b->scope_type === 'PLATFORM' || ($b->scope_type === 'TENANT' && $b->scope_value === $hqId) || ($b->scope_type === 'CHANNEL' && $b->scope_value === $channel));
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $context @return array<string, mixed> */
    private function commitment(array $row, array $context): array
    {
        $policy = is_string($row['sla_policy'] ?? null) ? json_decode($row['sla_policy'], true) : (array) ($row['sla_policy'] ?? []);
        $start = CarbonImmutable::parse((string) ($context['acceptance_at'] ?? now()->toISOString()))->utc();
        $value = max(0, (int) ($policy['duration_value'] ?? 0));
        $end = match ($policy['duration_unit'] ?? 'HOUR') { 'MINUTE' => $start->addMinutes($value), 'DAY' => $start->addDays($value), default => $start->addHours($value) };
        return ['commitment_type' => $policy['commitment_type'] ?? 'DURATION', 'starts_at' => $start->toISOString(), 'delivery_commitment_at' => $end->toISOString(), 'policy' => $policy,
            'pickup' => ['mode' => 'NONE'],
            'delivery' => ['mode' => 'COMPUTED', 'computed_at' => $end->toISOString()],
        ];
    }

    private function assertAccess(AuthenticatedPrincipal $actor, string $permission, bool $runtime = false): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        $modules = collect($context['module_entitlements']);
        $entitled = $modules->contains(fn ($e) => in_array($e['module_code'], $runtime ? ['ServiceCatalog', 'Consignment'] : ['ServiceCatalog'], true) && $e['status'] === 'ENABLED');
        if (! $entitled) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
    }

    private function visibleIdentity(AuthenticatedPrincipal $actor, string $table, string $id, string $value): void
    {
        if (! DB::table($table)->where($id, $value)->where(fn ($q) => $q->whereNull('hq_id')->orWhere('hq_id', $actor->hqId))->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
    }

    private function latestVersionId(string $table, string $id, string $parent, string $parentValue): string
    {
        return (string) DB::table($table)->where($parent, $parentValue)->orderByDesc('version_number')->value($id);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function decode(array $row): array
    {
        foreach (['labels', 'definition', 'sla_policy', 'availability_summary', 'condition', 'expected_value'] as $field) if (isset($row[$field]) && is_string($row[$field])) $row[$field] = json_decode($row[$field], true);
        return $row;
    }

    /** @param array<string, mixed> $after */
    private function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $correlationId, array $after): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, after: $after, sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, $type, $id, 'service.catalog.changed', $correlationId, [
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
        ]);
    }
}
