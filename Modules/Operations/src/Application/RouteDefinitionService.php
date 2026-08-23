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

final readonly class RouteDefinitionService
{
    public function __construct(
        private NetworkAccessGuard $access,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string,mixed> $filters */
    public function list(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        $query = DB::table('route_definitions')->where('hq_id', $hq);
        if (($filters['search'] ?? null) !== null) $query->where(fn ($q) => $q->where('route_code', 'like', '%'.$filters['search'].'%')->orWhere('route_title', 'like', '%'.$filters['search'].'%'));
        if (($filters['purpose'] ?? null) !== null) $query->whereExists(fn ($q) => $q->selectRaw('1')->from('route_definition_versions as v')->whereColumn('v.route_definition_id', 'route_definitions.route_definition_id')->where('v.purpose', $filters['purpose']));
        return $query->orderBy('route_code')->paginate((int) ($filters['per_page'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.route.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $input, $correlationId): string {
            if (DB::table('route_definitions')->where(['hq_id' => $hq, 'route_code' => $input['route_code']])->exists()) throw new ApiException(ApiErrorCode::Conflict, 409, 'The Route Definition code already exists.');
            $id = (string) Str::uuid();
            DB::table('route_definitions')->insert(['route_definition_id' => $id, 'hq_id' => $hq, 'route_code' => $input['route_code'], 'route_title' => $input['route_title'], 'status' => 'INACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->record($actor, 'ROUTE_DEFINITION_CREATED', 'ROUTE_DEFINITION', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->definition($actor, $id);
    }

    /** @return array<string,mixed> */
    public function definition(AuthenticatedPrincipal $actor, string $id): array
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        $row = DB::table('route_definitions')->where(['hq_id' => $hq, 'route_definition_id' => $id])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $this->definitionArray($row);
    }

    public function history(AuthenticatedPrincipal $actor, string $definitionId, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        if (! DB::table('route_definitions')->where(['hq_id' => $hq, 'route_definition_id' => $definitionId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return DB::table('route_definition_versions')->where(['hq_id' => $hq, 'route_definition_id' => $definitionId])->orderByDesc('version_number')->paginate($perPage, ['*'], 'page', $page);
    }

    /** @return array<string,mixed> */
    public function presentVersion(object $row): array { return $this->versionArray($row); }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createVersion(AuthenticatedPrincipal $actor, string $definitionId, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.route.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $definitionId, $input, $correlationId): string {
            if (! DB::table('route_definitions')->where(['hq_id' => $hq, 'route_definition_id' => $definitionId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $legs = (array) ($input['legs'] ?? []);
            if (($input['source_version_id'] ?? null) !== null && $legs === []) $legs = $this->legInputs($hq, (string) $input['source_version_id']);
            $this->validateContent($hq, $input, $legs);
            $id = (string) Str::uuid(); $number = ((int) DB::table('route_definition_versions')->where('route_definition_id', $definitionId)->max('version_number')) + 1;
            DB::table('route_definition_versions')->insert(['route_definition_version_id' => $id, 'hq_id' => $hq, 'route_definition_id' => $definitionId, 'version_number' => $number, 'status' => 'DRAFT', 'purpose' => $input['purpose'], 'origin_node_id' => $input['origin_node_id'], 'destination_node_id' => $input['destination_node_id'], 'priority' => $input['priority'], 'offering_version_id' => $input['offering_version_id'] ?? null, 'effective_from' => $input['effective_from'] ?? null, 'effective_to' => $input['effective_to'] ?? null, 'version' => 1, 'created_by' => $actor->userId, 'created_at' => now(), 'updated_at' => now()]);
            $this->replaceLegs($hq, $id, $legs);
            $this->record($actor, 'ROUTE_VERSION_CREATED', 'ROUTE_DEFINITION_VERSION', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->version($actor, $definitionId, $id);
    }

    /** @return array<string,mixed> */
    public function version(AuthenticatedPrincipal $actor, string $definitionId, string $versionId): array
    {
        $hq = $this->access->assert($actor, 'network.route.view');
        return $this->versionArray($this->versionRow($hq, $definitionId, $versionId));
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function update(AuthenticatedPrincipal $actor, string $definitionId, string $versionId, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.route.manage_draft');
        $this->transactions->run(function () use ($actor, $hq, $definitionId, $versionId, $input, $correlationId): void {
            $row = $this->lockedVersion($hq, $definitionId, $versionId);
            if ($row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft version is editable.');
            $this->expected($row, (int) $input['expected_version']);
            $candidate = array_merge((array) $row, $input); $legs = array_key_exists('legs', $input) ? $input['legs'] : $this->legInputs($hq, $versionId);
            $this->validateContent($hq, $candidate, $legs);
            $changes = ['version' => (int) $row->version + 1, 'updated_at' => now()];
            foreach (['priority', 'offering_version_id', 'effective_from', 'effective_to'] as $field) if (array_key_exists($field, $input)) $changes[$field] = $input[$field];
            if (array_key_exists('legs', $input)) $this->replaceLegs($hq, $versionId, $legs);
            DB::table('route_definition_versions')->where('route_definition_version_id', $versionId)->update($changes);
            $this->record($actor, 'ROUTE_VERSION_UPDATED', 'ROUTE_DEFINITION_VERSION', $versionId, 'DRAFT', $correlationId);
        });
        return $this->version($actor, $definitionId, $versionId);
    }

    /** @return array<string,mixed> */
    public function transition(AuthenticatedPrincipal $actor, string $definitionId, string $versionId, string $action, int $expected, ?string $note, string $correlationId): array
    {
        $permission = match ($action) { 'validate' => 'network.route.validate', 'approve' => 'network.route.approve', 'publish', 'supersede' => 'network.route.publish', default => 'network.route.manage_draft' };
        $hq = $this->access->assert($actor, $permission);
        $this->transactions->run(function () use ($actor, $hq, $definitionId, $versionId, $action, $expected, $note, $correlationId): void {
            $row = $this->lockedVersion($hq, $definitionId, $versionId); $this->expected($row, $expected);
            $next = match ($action) {
                'validate' => $this->validatedChanges($hq, $row, $actor->userId),
                'approve' => $this->simpleChanges($row, 'VALIDATED', 'APPROVED', ['approved_by' => $actor->userId, 'approved_at' => now()]),
                'publish' => $this->publishedChanges($hq, $row, $actor->userId),
                'supersede' => $this->simpleChanges($row, 'PUBLISHED', 'SUPERSEDED'),
                'archive' => $this->archiveChanges($row),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unknown lifecycle action.'),
            };
            $next['version'] = (int) $row->version + 1; $next['updated_at'] = now();
            DB::table('route_definition_versions')->where('route_definition_version_id', $versionId)->update($next);
            if ($action === 'publish') { DB::table('route_definitions')->where('route_definition_id', $definitionId)->update(['published_version_id' => $versionId, 'status' => 'ACTIVE', 'version' => DB::raw('version + 1'), 'updated_at' => now()]); $this->syncLegacyLegs($row); }
            if ($action === 'supersede') DB::table('route_definitions')->where(['route_definition_id' => $definitionId, 'published_version_id' => $versionId])->update(['published_version_id' => null, 'status' => 'INACTIVE', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
            $this->record($actor, 'ROUTE_VERSION_'.strtoupper($action), 'ROUTE_DEFINITION_VERSION', $versionId, (string) $next['status'], $correlationId, $note);
        });
        return $this->version($actor, $definitionId, $versionId);
    }

    /** @return array<string,mixed> */
    public function resolve(string $hqId, string $purpose, string $originNodeId, string $destinationNodeId, ?string $offeringVersionId = null, ?Carbon $at = null): array
    {
        $at ??= now();
        $matches = DB::table('route_definition_versions as v')->join('route_definitions as d', 'd.route_definition_id', '=', 'v.route_definition_id')->where(['v.hq_id' => $hqId, 'v.status' => 'PUBLISHED', 'v.purpose' => $purpose, 'v.origin_node_id' => $originNodeId, 'v.destination_node_id' => $destinationNodeId])->whereColumn('d.published_version_id', 'v.route_definition_version_id')->where(fn ($q) => $q->whereNull('v.effective_from')->orWhere('v.effective_from', '<=', $at))->where(fn ($q) => $q->whereNull('v.effective_to')->orWhere('v.effective_to', '>', $at))->where(fn ($q) => $q->whereNull('v.offering_version_id')->when($offeringVersionId !== null, fn ($inner) => $inner->orWhere('v.offering_version_id', $offeringVersionId)))->orderByDesc('v.priority')->get(['v.*']);
        if ($matches->isEmpty()) throw new ApiException(ApiErrorCode::RouteNotFound, 422, 'No published Route Definition matches the request.');
        $best = $matches->first(); $ties = $matches->where('priority', $best->priority);
        if ($ties->count() > 1) throw new ApiException(ApiErrorCode::RouteAmbiguous, 422, 'More than one published Route Definition has the best priority.', details: ['route_definition_version_ids' => $ties->pluck('route_definition_version_id')->all()]);
        return $this->versionArray($best);
    }

    /** @param array<string,mixed> $content @param list<array<string,mixed>> $legs */
    private function validateContent(string $hq, array $content, array $legs): void
    {
        if (! in_array($content['purpose'] ?? null, ['TRUNK', 'LAST_MILE'], true) || ! isset($content['origin_node_id'], $content['destination_node_id'], $content['priority']) || $legs === []) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Route Version is incomplete.');
        if ((int) $content['priority'] < -100000 || (int) $content['priority'] > 100000) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route priority is outside the supported range.');
        if (($content['offering_version_id'] ?? null) !== null && ! DB::table('service_offering_versions as v')->join('service_offerings as i', 'i.service_offering_id', '=', 'v.service_offering_id')->where('v.service_offering_version_id', $content['offering_version_id'])->where(fn ($q) => $q->whereNull('i.hq_id')->orWhere('i.hq_id', $hq))->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Offering Version is not visible to this HQ.');
        $seen = []; $previous = null;
        foreach (array_values($legs) as $index => $leg) {
            if ((int) ($leg['leg_order'] ?? 0) !== $index + 1) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route leg_order values must be contiguous and start at one.');
            if ($previous !== null && ($leg['origin_node_id'] ?? null) !== $previous) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Route leg chain is broken.');
            foreach (['origin_node_id', 'destination_node_id'] as $field) if (! DB::table('nodes')->where(['hq_id' => $hq, 'node_id' => $leg[$field] ?? '', 'status' => 'ACTIVE'])->exists()) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every Route Node must be active and belong to the current HQ.');
            if ($leg['origin_node_id'] === $leg['destination_node_id'] || isset($seen[$leg['destination_node_id']])) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route templates cannot contain cycles.');
            $seen[$leg['origin_node_id']] = true; $previous = $leg['destination_node_id'];
        }
        if ($legs[0]['origin_node_id'] !== $content['origin_node_id'] || $legs[count($legs) - 1]['destination_node_id'] !== $content['destination_node_id']) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route endpoints must match the first and last Leg.');
        if (($content['effective_from'] ?? null) !== null && ($content['effective_to'] ?? null) !== null && Carbon::parse($content['effective_from'])->gte(Carbon::parse($content['effective_to']))) throw new ApiException(ApiErrorCode::ValidationError, 422, 'effective_to must be after effective_from.');
    }

    /** @param list<array<string,mixed>> $legs */
    private function replaceLegs(string $hq, string $versionId, array $legs): void { DB::table('route_definition_version_legs')->where('route_definition_version_id', $versionId)->delete(); foreach ($legs as $leg) DB::table('route_definition_version_legs')->insert(['route_definition_version_leg_id' => (string) Str::uuid(), 'hq_id' => $hq, 'route_definition_version_id' => $versionId, 'leg_order' => $leg['leg_order'], 'origin_node_id' => $leg['origin_node_id'], 'destination_node_id' => $leg['destination_node_id'], 'created_at' => now(), 'updated_at' => now()]); }
    /** @return list<array<string,mixed>> */
    private function legInputs(string $hq, string $versionId): array { if (! DB::table('route_definition_versions')->where(['hq_id' => $hq, 'route_definition_version_id' => $versionId])->exists()) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Source version not found.'); return DB::table('route_definition_version_legs')->where('route_definition_version_id', $versionId)->orderBy('leg_order')->get()->map(fn ($leg): array => ['leg_order' => (int) $leg->leg_order, 'origin_node_id' => (string) $leg->origin_node_id, 'destination_node_id' => (string) $leg->destination_node_id])->all(); }
    private function versionRow(string $hq, string $definition, string $version): object { $row = DB::table('route_definition_versions')->where(['hq_id' => $hq, 'route_definition_id' => $definition, 'route_definition_version_id' => $version])->first(); if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'); return $row; }
    private function lockedVersion(string $hq, string $definition, string $version): object { $row = DB::table('route_definition_versions')->where(['hq_id' => $hq, 'route_definition_id' => $definition, 'route_definition_version_id' => $version])->lockForUpdate()->first(); if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'); return $row; }
    private function expected(object $row, int $expected): void { if ((int) $row->version !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Route Version is stale.', details: ['current_version' => (int) $row->version]); }
    /** @return array<string,mixed> */
    private function validatedChanges(string $hq, object $row, string $user): array { if ($row->status !== 'DRAFT') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft can be validated.'); $this->validateContent($hq, (array) $row, $this->legInputs($hq, $row->route_definition_version_id)); return ['status' => 'VALIDATED', 'validated_by' => $user, 'validated_at' => now()]; }
    /** @param array<string,mixed> $extra @return array<string,mixed> */
    private function simpleChanges(object $row, string $from, string $to, array $extra = []): array { if ($row->status !== $from) throw new ApiException(ApiErrorCode::ValidationError, 422, "Only {$from} can transition to {$to}."); return ['status' => $to, ...$extra]; }
    /** @return array<string,mixed> */
    private function archiveChanges(object $row): array { if (! in_array($row->status, ['DRAFT', 'VALIDATED', 'APPROVED'], true)) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published or superseded versions cannot be archived.'); return ['status' => 'ARCHIVED']; }
    /** @return array<string,mixed> */
    private function publishedChanges(string $hq, object $row, string $user): array { if ($row->status !== 'APPROVED') throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.'); $this->validateContent($hq, (array) $row, $this->legInputs($hq, $row->route_definition_version_id)); return ['status' => 'PUBLISHED', 'published_by' => $user, 'published_at' => now(), 'content_digest' => hash('sha256', json_encode($this->versionArray($row), JSON_THROW_ON_ERROR))]; }
    private function syncLegacyLegs(object $version): void { DB::table('route_definition_legs')->where('route_definition_id', $version->route_definition_id)->delete(); foreach (DB::table('route_definition_version_legs')->where('route_definition_version_id', $version->route_definition_version_id)->orderBy('leg_order')->get() as $leg) DB::table('route_definition_legs')->insert(['route_definition_leg_id' => (string) Str::uuid(), 'hq_id' => $version->hq_id, 'route_definition_id' => $version->route_definition_id, 'leg_order' => $leg->leg_order, 'origin_node_id' => $leg->origin_node_id, 'destination_node_id' => $leg->destination_node_id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]); }
    /** @return array<string,mixed> */
    private function definitionArray(object $row): array { return ['route_definition_id' => (string) $row->route_definition_id, 'route_code' => (string) $row->route_code, 'route_title' => (string) $row->route_title, 'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null]; }
    /** @return array<string,mixed> */
    private function versionArray(object $row): array { return ['route_definition_version_id' => (string) $row->route_definition_version_id, 'route_definition_id' => (string) $row->route_definition_id, 'version_number' => (int) $row->version_number, 'status' => (string) $row->status, 'purpose' => (string) $row->purpose, 'origin_node_id' => (string) $row->origin_node_id, 'destination_node_id' => (string) $row->destination_node_id, 'priority' => (int) $row->priority, 'offering_version_id' => $row->offering_version_id ? (string) $row->offering_version_id : null, 'effective_from' => $row->effective_from, 'effective_to' => $row->effective_to, 'version' => (int) $row->version, 'legs' => DB::table('route_definition_version_legs')->where('route_definition_version_id', $row->route_definition_version_id)->orderBy('leg_order')->get()->map(fn ($leg): array => ['route_definition_leg_id' => (string) $leg->route_definition_version_leg_id, 'leg_order' => (int) $leg->leg_order, 'origin_node_id' => (string) $leg->origin_node_id, 'destination_node_id' => (string) $leg->destination_node_id])->all()]; }
    private function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $status, string $correlationId, ?string $note = null): void { $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, safeNote: $note, sourceClient: 'BRANCH_PANEL'); $this->outbox->write($actor->hqId, $type, $id, 'network.configuration.changed', $correlationId, ['action' => $action, 'target_type' => $type, 'target_id' => $id, 'status' => $status]); }
}
