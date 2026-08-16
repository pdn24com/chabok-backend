<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

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

final readonly class CommitmentScheduleService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string,mixed> $filters */
    public function list(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->assertAccess($actor, 'service_catalog.view');
        $query = DB::table('commitment_schedules as s')->where('s.hq_id', $actor->hqId)
            ->select(['s.*'])
            ->selectSub(DB::table('commitment_schedule_versions as v')->select('v.status')->whereColumn('v.commitment_schedule_id', 's.commitment_schedule_id')->orderByDesc('v.version_number')->limit(1), 'latest_status')
            ->selectSub(DB::table('commitment_schedule_versions as v')->select('v.version_number')->whereColumn('v.commitment_schedule_id', 's.commitment_schedule_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_number')
            ->selectSub(DB::table('commitment_schedule_versions as v')->select('v.commitment_schedule_version_id')->whereColumn('v.commitment_schedule_id', 's.commitment_schedule_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_id');
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('s.code', 'like', $search)->orWhere('s.title', 'like', $search));
        }

        return $query->orderBy('s.code')->paginate(
            min(100, max(1, (int) ($filters['page_size'] ?? 25))),
            page: max(1, (int) ($filters['page'] ?? 1)),
        );
    }

    /** @return list<array<string,mixed>> */
    public function published(AuthenticatedPrincipal $actor): array
    {
        $this->assertAccess($actor, 'service_catalog.view');
        return DB::table('commitment_schedule_versions as v')
            ->join('commitment_schedules as s', 's.commitment_schedule_id', '=', 'v.commitment_schedule_id')
            ->where(['s.hq_id' => $actor->hqId, 'v.status' => 'PUBLISHED'])
            ->orderBy('s.code')->get(['v.*', 's.code', 's.title'])
            ->map(fn ($row) => $this->versionDetail($actor, (string) $row->commitment_schedule_version_id))->all();
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $identityId = (string) Str::uuid();
            $versionId = (string) Str::uuid();
            $now = now();
            DB::table('commitment_schedules')->insert([
                'commitment_schedule_id' => $identityId, 'hq_id' => $actor->hqId,
                'owner_key' => $actor->hqId, 'code' => Str::upper((string) $input['code']),
                'title' => $input['title'], 'status' => 'ACTIVE', 'created_by' => $actor->userId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('commitment_schedule_versions')->insert([
                'commitment_schedule_version_id' => $versionId, 'commitment_schedule_id' => $identityId,
                'hq_id' => $actor->hqId, 'version_number' => 1, 'previous_version_id' => null,
                'status' => 'DRAFT', ...$this->versionColumns($input), 'lock_version' => 1,
                'created_by' => $actor->userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->replaceChildren($versionId, (string) $actor->hqId, $input);
            $this->record($actor, 'COMMITMENT_SCHEDULE_CREATED', $identityId, $correlationId, ['version_id' => $versionId]);
            return $this->versionDetail($actor, $versionId);
        });
    }

    /** @return array<string,mixed> */
    public function cloneDraft(AuthenticatedPrincipal $actor, string $identityId, string $correlationId): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        return $this->transactions->run(function () use ($actor, $identityId, $correlationId): array {
            if (! DB::table('commitment_schedules')->where(['hq_id' => $actor->hqId, 'commitment_schedule_id' => $identityId])->exists()) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if (DB::table('commitment_schedule_versions')->where('commitment_schedule_id', $identityId)->whereIn('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED'])->exists()) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'An unpublished successor already exists.');
            }
            $previous = (array) DB::table('commitment_schedule_versions')->where('commitment_schedule_id', $identityId)->orderByDesc('version_number')->lockForUpdate()->first();
            if ($previous === []) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $previousId = (string) $previous['commitment_schedule_version_id'];
            $newId = (string) Str::uuid();
            unset($previous['approved_by'], $previous['published_by'], $previous['approved_at'], $previous['published_at'], $previous['content_digest']);
            $previous['commitment_schedule_version_id'] = $newId;
            $previous['previous_version_id'] = $previousId;
            $previous['version_number'] = ((int) $previous['version_number']) + 1;
            $previous['status'] = 'DRAFT'; $previous['lock_version'] = 1;
            $previous['valid_from'] = null; $previous['valid_to'] = null;
            $previous['created_by'] = $actor->userId; $previous['created_at'] = now(); $previous['updated_at'] = now();
            DB::table('commitment_schedule_versions')->insert($previous);
            foreach (DB::table('commitment_schedule_windows')->where('commitment_schedule_version_id', $previousId)->get() as $row) {
                $copy = (array) $row; $copy['commitment_schedule_window_id'] = (string) Str::uuid(); $copy['commitment_schedule_version_id'] = $newId; DB::table('commitment_schedule_windows')->insert($copy);
            }
            foreach (DB::table('commitment_schedule_scopes')->where('commitment_schedule_version_id', $previousId)->get() as $row) {
                $copy = (array) $row; $copy['commitment_schedule_scope_id'] = (string) Str::uuid(); $copy['commitment_schedule_version_id'] = $newId; DB::table('commitment_schedule_scopes')->insert($copy);
            }
            $this->record($actor, 'COMMITMENT_SCHEDULE_DRAFT_CLONED', $identityId, $correlationId, ['version_id' => $newId, 'previous_version_id' => $previousId]);
            return $this->versionDetail($actor, $newId);
        });
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(AuthenticatedPrincipal $actor, string $versionId, array $input, string $correlationId): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        return $this->transactions->run(function () use ($actor, $versionId, $input, $correlationId): array {
            $row = DB::table('commitment_schedule_versions')->where(['commitment_schedule_version_id' => $versionId, 'hq_id' => $actor->hqId])->lockForUpdate()->first();
            if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if ((string) $row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only draft schedule versions can be edited.');
            if ((int) $row->lock_version !== (int) $input['expected_version']) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The schedule changed since it was loaded.', details: ['current_version' => (int) $row->lock_version]);
            DB::table('commitment_schedules')
                ->where(['commitment_schedule_id' => $row->commitment_schedule_id, 'hq_id' => $actor->hqId])
                ->update(['title' => $input['title'], 'updated_at' => now()]);
            DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id', $versionId)->update($this->versionColumns($input) + ['lock_version' => ((int) $row->lock_version) + 1, 'updated_at' => now()]);
            $this->replaceChildren($versionId, (string) $actor->hqId, $input);
            $this->record($actor, 'COMMITMENT_SCHEDULE_DRAFT_UPDATED', (string) $row->commitment_schedule_id, $correlationId, ['version_id' => $versionId]);
            return $this->versionDetail($actor, $versionId);
        });
    }

    /** @return array<string,mixed> */
    public function validate(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $this->assertAccess($actor, 'service_catalog.manage_draft');
        $version = $this->versionDetail($actor, $versionId); $errors = [];
        if ($version['windows'] === []) $errors[] = ['code' => 'COMMITMENT_WINDOW_REQUIRED', 'field' => 'windows'];
        if (! collect($version['windows'])->contains(fn ($window) => $window['window_type'] === 'PICKUP')) $errors[] = ['code' => 'PICKUP_WINDOW_REQUIRED', 'field' => 'windows'];
        foreach ($version['windows'] as $index => $window) {
            if ($window['start_time'] >= $window['end_time']) $errors[] = ['code' => 'COMMITMENT_WINDOW_INTERVAL_INVALID', 'field' => "windows.{$index}.end_time"];
            if ($window['applicable_weekdays'] === []) $errors[] = ['code' => 'COMMITMENT_WEEKDAY_REQUIRED', 'field' => "windows.{$index}.applicable_weekdays"];
        }
        if ($version['scopes'] === []) $errors[] = ['code' => 'COMMITMENT_SCOPE_REQUIRED', 'field' => 'scopes'];
        if ($version['valid_from'] && $version['valid_to'] && $version['valid_to'] <= $version['valid_from']) $errors[] = ['code' => 'COMMITMENT_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        $overlap = DB::table('commitment_schedule_versions')->where('commitment_schedule_id', $version['commitment_schedule_id'])->where('commitment_schedule_version_id', '!=', $versionId)->whereIn('status', ['APPROVED', 'PUBLISHED'])
            ->when($version['valid_from'], fn ($q) => $q->where(fn ($nested) => $nested->whereNull('valid_to')->orWhere('valid_to', '>', $version['valid_from'])))
            ->when($version['valid_to'], fn ($q) => $q->where(fn ($nested) => $nested->whereNull('valid_from')->orWhere('valid_from', '<', $version['valid_to'])))->exists();
        if ($overlap) $errors[] = ['code' => 'COMMITMENT_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        return ['valid' => $errors === [], 'errors' => $errors];
    }

    /** @return array<string,mixed> */
    public function transition(AuthenticatedPrincipal $actor, string $versionId, string $action, string $correlationId): array
    {
        $this->assertAccess($actor, $action === 'approve' ? 'service_catalog.approve' : ($action === 'publish' ? 'service_catalog.publish' : 'service_catalog.manage_draft'));
        return $this->transactions->run(function () use ($actor, $versionId, $action, $correlationId): array {
            $row = DB::table('commitment_schedule_versions')->where(['commitment_schedule_version_id' => $versionId, 'hq_id' => $actor->hqId])->lockForUpdate()->first();
            if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if ($action === 'approve') {
                if ((string) $row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft schedule can be approved.');
                $validation = $this->validate($actor, $versionId);
                if (! $validation['valid']) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Commitment schedule validation failed.', details: $validation);
                $changes = ['status' => 'APPROVED', 'approved_by' => $actor->userId, 'approved_at' => now()];
            } elseif ($action === 'publish') {
                if ((string) $row->status !== 'APPROVED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved schedule can be published.');
                $detail = $this->versionDetail($actor, $versionId);
                $changes = ['status' => 'PUBLISHED', 'published_by' => $actor->userId, 'published_at' => now(), 'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
            } elseif ($action === 'supersede' && (string) $row->status === 'PUBLISHED') $changes = ['status' => 'SUPERSEDED'];
            elseif ($action === 'archive' && (string) $row->status === 'SUPERSEDED') $changes = ['status' => 'ARCHIVED'];
            else throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unsupported schedule lifecycle transition.');
            DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id', $versionId)->update($changes + ['updated_at' => now()]);
            $this->record($actor, 'COMMITMENT_SCHEDULE_VERSION_'.Str::upper($action).'D', (string) $row->commitment_schedule_id, $correlationId, ['version_id' => $versionId, 'status' => $changes['status']]);
            return $this->versionDetail($actor, $versionId);
        });
    }

    /** @return list<array<string,mixed>> */
    public function history(AuthenticatedPrincipal $actor, string $identityId): array
    {
        $this->assertAccess($actor, 'service_catalog.history.view');
        if (! DB::table('commitment_schedules')->where(['hq_id' => $actor->hqId, 'commitment_schedule_id' => $identityId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return DB::table('commitment_schedule_versions')->where('commitment_schedule_id', $identityId)->orderByDesc('version_number')->pluck('commitment_schedule_version_id')->map(fn ($id) => $this->versionDetail($actor, (string) $id))->all();
    }

    /** @return list<array<string,mixed>> */
    public function pickupWindows(AuthenticatedPrincipal $actor, string $nodeId, ?string $at = null): array
    {
        $this->assertAccess($actor, 'service_catalog.resolve', true);
        $now = CarbonImmutable::parse($at ?? now()->toISOString());
        $versionIds = DB::table('commitment_schedule_versions as v')->join('commitment_schedule_scopes as s', 's.commitment_schedule_version_id', '=', 'v.commitment_schedule_version_id')
            ->where(['v.hq_id' => $actor->hqId, 'v.status' => 'PUBLISHED'])
            ->where(fn ($q) => $q->where(fn ($scope) => $scope->where('s.scope_type', 'HQ'))->orWhere(fn ($scope) => $scope->where('s.scope_type', 'NODE')->where('s.node_id', $nodeId)))
            ->where(fn ($q) => $q->whereNull('v.valid_from')->orWhere('v.valid_from', '<=', $now->utc()))
            ->where(fn ($q) => $q->whereNull('v.valid_to')->orWhere('v.valid_to', '>', $now->utc()))
            ->distinct()->pluck('v.commitment_schedule_version_id');
        $results = [];
        foreach ($versionIds as $versionId) {
            $version = (array) DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id', $versionId)->first();
            foreach (DB::table('commitment_schedule_windows')->where(['commitment_schedule_version_id' => $versionId, 'window_type' => 'PICKUP', 'active' => true])->orderBy('start_time')->get() as $window) {
                $instance = $this->nextWindow((array) $window, (string) $version['timezone'], $now);
                if ($instance !== null) $results[] = ['commitment_schedule_version_id' => $versionId, ...$instance];
            }
        }
        usort($results, fn ($left, $right) => strcmp((string) $left['starts_at'], (string) $right['starts_at']));
        return $results;
    }

    /** @param array<string,mixed> $context @return array<string,mixed>|null */
    public function resolveForOffering(string $offeringVersionId, array $context): ?array
    {
        $binding = DB::table('service_offering_commitment_bindings')->where('service_offering_version_id', $offeringVersionId)->first();
        if ($binding === null) return null;
        $version = DB::table('commitment_schedule_versions')->where(['commitment_schedule_version_id' => $binding->commitment_schedule_version_id, 'status' => 'PUBLISHED'])->first();
        if ($version === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The bound commitment schedule is not published.', details: ['reason_code' => 'COMMITMENT_SCHEDULE_NOT_PUBLISHED']);
        $serviceDate = (string) ($context['pickup_service_date'] ?? CarbonImmutable::now((string) $version->timezone)->toDateString());
        $pickup = null;
        if ($binding->pickup_mode === 'SELECTABLE_WINDOW') {
            $code = (string) ($context['pickup_window_code'] ?? '');
            if ($code === '') return ['eligible' => false, 'reason_code' => 'PICKUP_WINDOW_REQUIRED', 'binding' => (array) $binding, 'pickup' => null, 'delivery' => null];
            $pickup = $this->windowInstance((string) $version->commitment_schedule_version_id, 'PICKUP', $code, $serviceDate, (string) $version->timezone);
        }
        $delivery = ['mode' => (string) $binding->delivery_mode];
        if ($binding->delivery_mode === 'SELECTABLE_WINDOW') {
            $delivery['windows'] = DB::table('commitment_schedule_windows')->where(['commitment_schedule_version_id' => $version->commitment_schedule_version_id, 'window_type' => 'DELIVERY', 'active' => true])->orderBy('day_offset')->orderBy('start_time')->get()->map(fn ($window) => $this->windowInstance((string) $version->commitment_schedule_version_id, 'DELIVERY', (string) $window->window_code, $serviceDate, (string) $version->timezone))->all();
            $selectedCode = (string) ($context['delivery_window_code'] ?? '');
            $delivery['selected'] = $selectedCode === '' ? null : $this->windowInstance((string) $version->commitment_schedule_version_id, 'DELIVERY', $selectedCode, $serviceDate, (string) $version->timezone);
        } elseif ($binding->delivery_mode === 'COMPUTED') {
            $anchor = match ((string) $binding->duration_anchor) {
                'CONSIGNMENT_CREATED' => CarbonImmutable::parse((string) ($context['acceptance_at'] ?? now()->toISOString()))->utc(),
                'PICKUP_COMMITMENT_START' => isset($pickup['starts_at']) ? CarbonImmutable::parse($pickup['starts_at']) : null,
                'PICKUP_COMMITMENT_END' => isset($pickup['ends_at']) ? CarbonImmutable::parse($pickup['ends_at']) : null,
                default => null,
            };
            $computed = $anchor === null ? null : match ((string) $binding->duration_unit) {
                'MINUTE' => $anchor->addMinutes((int) $binding->duration_value),
                'DAY' => $anchor->addDays((int) $binding->duration_value),
                default => $anchor->addHours((int) $binding->duration_value),
            };
            $delivery += ['anchor' => (string) $binding->duration_anchor, 'duration_value' => $binding->duration_value, 'duration_unit' => $binding->duration_unit, 'computed_at' => $computed?->toISOString(), 'awaiting_operation' => $binding->duration_anchor === 'PICKUP_COMPLETED'];
        }
        return ['eligible' => true, 'reason_code' => null, 'schedule_version_id' => (string) $version->commitment_schedule_version_id, 'timezone' => (string) $version->timezone, 'binding' => (array) $binding, 'pickup' => $pickup, 'delivery' => $delivery];
    }

    /** @return array<string,mixed> */
    public function versionDetail(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $row = DB::table('commitment_schedule_versions as v')->join('commitment_schedules as s', 's.commitment_schedule_id', '=', 'v.commitment_schedule_id')->where(['v.commitment_schedule_version_id' => $versionId, 's.hq_id' => $actor->hqId])->select(['v.*', 's.code', 's.title'])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $result = (array) $row;
        $result['windows'] = DB::table('commitment_schedule_windows')->where('commitment_schedule_version_id', $versionId)->orderBy('window_type')->orderBy('start_time')->get()->map(fn ($window) => $this->decodeWindow((array) $window))->all();
        $result['scopes'] = DB::table('commitment_schedule_scopes')->where('commitment_schedule_version_id', $versionId)->get()->map(fn ($scope) => (array) $scope)->all();
        return $result;
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function versionColumns(array $input): array
    {
        return ['timezone' => $input['timezone'] ?? 'Asia/Tehran', 'calendar_code' => $input['calendar_code'] ?? 'IR_STANDARD', 'valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null), 'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null)];
    }

    /** @param array<string,mixed> $input */
    private function replaceChildren(string $versionId, string $hqId, array $input): void
    {
        DB::table('commitment_schedule_windows')->where('commitment_schedule_version_id', $versionId)->delete();
        DB::table('commitment_schedule_scopes')->where('commitment_schedule_version_id', $versionId)->delete();
        foreach ((array) ($input['windows'] ?? []) as $window) DB::table('commitment_schedule_windows')->insert([
            'commitment_schedule_window_id' => (string) Str::uuid(), 'commitment_schedule_version_id' => $versionId,
            'window_code' => Str::upper((string) $window['window_code']), 'window_type' => $window['window_type'], 'label_fa' => $window['label_fa'],
            'start_time' => $window['start_time'], 'end_time' => $window['end_time'], 'booking_cutoff_time' => $window['booking_cutoff_time'],
            'applicable_weekdays' => json_encode(array_values((array) $window['applicable_weekdays']), JSON_THROW_ON_ERROR),
            'day_offset' => $window['day_offset'] ?? 0, 'active' => $window['active'] ?? true,
        ]);
        foreach ((array) ($input['scopes'] ?? [['scope_type' => 'HQ']]) as $scope) DB::table('commitment_schedule_scopes')->insert([
            'commitment_schedule_scope_id' => (string) Str::uuid(), 'commitment_schedule_version_id' => $versionId, 'hq_id' => $hqId,
            'scope_type' => $scope['scope_type'], 'node_id' => $scope['scope_type'] === 'NODE' ? $scope['node_id'] : null,
        ]);
    }

    /** @param array<string,mixed> $window @return array<string,mixed> */
    private function decodeWindow(array $window): array
    {
        if (is_string($window['applicable_weekdays'] ?? null)) $window['applicable_weekdays'] = json_decode($window['applicable_weekdays'], true);
        return $window;
    }

    /** @param array<string,mixed> $window @return array<string,mixed>|null */
    private function nextWindow(array $window, string $timezone, CarbonImmutable $now): ?array
    {
        $localNow = $now->setTimezone($timezone); $days = (array) $this->decodeWindow($window)['applicable_weekdays'];
        for ($offset = 0; $offset < 14; $offset++) {
            $date = $localNow->startOfDay()->addDays($offset + (int) ($window['day_offset'] ?? 0));
            if (! in_array($date->dayOfWeekIso, array_map('intval', $days), true)) continue;
            $cutoff = CarbonImmutable::parse($date->toDateString().' '.$window['booking_cutoff_time'], $timezone);
            if ($localNow->greaterThan($cutoff)) continue;
            return $this->instancePayload($window, $date, $timezone, $cutoff);
        }
        return null;
    }

    /** @return array<string,mixed> */
    private function windowInstance(string $versionId, string $type, string $code, string $serviceDate, string $timezone): array
    {
        $window = DB::table('commitment_schedule_windows')->where(['commitment_schedule_version_id' => $versionId, 'window_type' => $type, 'window_code' => $code, 'active' => true])->first();
        if ($window === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The commitment window is not valid for this Offering.', details: ['reason_code' => "{$type}_WINDOW_INVALID"]);
        $row = $this->decodeWindow((array) $window); $date = CarbonImmutable::parse($serviceDate, $timezone)->startOfDay()->addDays((int) ($row['day_offset'] ?? 0));
        if (! in_array($date->dayOfWeekIso, array_map('intval', (array) $row['applicable_weekdays']), true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The commitment window is not available on that date.', details: ['reason_code' => "{$type}_WINDOW_DATE_INVALID"]);
        $cutoff = CarbonImmutable::parse($date->toDateString().' '.$row['booking_cutoff_time'], $timezone);
        if ($type === 'PICKUP' && CarbonImmutable::now($timezone)->greaterThan($cutoff)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Pickup booking cut-off has passed.', details: ['reason_code' => 'PICKUP_CUTOFF_PASSED']);
        return $this->instancePayload($row, $date, $timezone, $cutoff);
    }

    /** @param array<string,mixed> $window @return array<string,mixed> */
    private function instancePayload(array $window, CarbonImmutable $date, string $timezone, CarbonImmutable $cutoff): array
    {
        return ['window_code' => (string) $window['window_code'], 'window_type' => (string) $window['window_type'], 'label_fa' => (string) $window['label_fa'], 'service_date' => $date->toDateString(), 'starts_at' => CarbonImmutable::parse($date->toDateString().' '.$window['start_time'], $timezone)->utc()->toISOString(), 'ends_at' => CarbonImmutable::parse($date->toDateString().' '.$window['end_time'], $timezone)->utc()->toISOString(), 'booking_cutoff_at' => $cutoff->utc()->toISOString(), 'timezone' => $timezone, 'day_offset' => (int) $window['day_offset']];
    }

    private function databaseTimestamp(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }

    private function assertAccess(AuthenticatedPrincipal $actor, string $permission, bool $runtime = false): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($item) => in_array($item['module_code'], $runtime ? ['ServiceCatalog', 'Consignment'] : ['ServiceCatalog'], true) && $item['status'] === 'ENABLED')) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
    }

    /** @param array<string,mixed> $after */
    private function record(AuthenticatedPrincipal $actor, string $action, string $id, string $correlationId, array $after): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, 'COMMITMENT_SCHEDULE', $id, $correlationId, after: $after, sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'COMMITMENT_SCHEDULE', $id, 'service.catalog.commitment-schedule.changed', $correlationId, ['action' => $action, 'target_id' => $id]);
    }
}
