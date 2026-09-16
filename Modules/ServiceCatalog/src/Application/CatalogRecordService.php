<?php
declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

/** Direct editing API; immutable technical revisions remain an implementation detail. */
final readonly class CatalogRecordService
{
    public function __construct(private ServiceCatalogService $catalog, private CommitmentScheduleService $schedules,
        private AuthorizationContextResolver $authorization, private AuditWriter $audit, private OutboxWriter $outbox) {}

    public function authorize(AuthenticatedPrincipal $actor, bool $write = false): void
    {
        $context = $this->authorization->resolve($actor);
        if ($actor->hqId === null || !collect($context['module_entitlements'])->contains(fn ($e) => $e['module_code'] === 'ServiceCatalog' && $e['status'] === 'ENABLED'))
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        // Immediate changes require both existing editing and effective-publication grants.
        foreach ($write ? ['service_catalog.view', 'service_catalog.manage_draft', 'service_catalog.publish'] : ['service_catalog.view'] as $permission)
            if (!in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
    }

    public function detail(AuthenticatedPrincipal $actor, string $resource, string $id): array
    {
        $this->authorize($actor);
        [$table, $versions, $identityId, $versionId] = CurrentCatalog::MAP[$resource];
        $identity = DB::table($table)->where([$identityId => $id, 'hq_id' => $actor->hqId])->first();
        if ($identity === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        $revisionId = (string) DB::table($versions)->where($identityId, $id)->orderByDesc('version_number')->value($versionId);
        $detail = $resource === 'commitment-schedules' ? $this->schedules->versionDetail($actor, $revisionId) : $this->catalog->versionDetail($actor, $resource, $revisionId);
        if ($resource === 'offerings') {
            foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $dependency) {
                [, $refs, $stable, $revision] = CurrentCatalog::MAP[$dependency];
                $detail[$stable] = DB::table($refs)->where($revision, $detail[$field])->value($stable);
            }
            if (!empty($detail['commitment_binding'])) $detail['commitment_binding']['commitment_schedule_id'] = DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id', $detail['commitment_binding']['commitment_schedule_version_id'])->value('commitment_schedule_id');
            foreach ($detail['option_rules'] ?? [] as $index => $rule) $detail['option_rules'][$index]['service_option_id'] = DB::table('service_option_versions')->where('service_option_version_id', $rule['service_option_version_id'])->value('service_option_id');
        }
        return [...$detail, 'status' => $identity->status, 'lock_version' => (int) $identity->edit_lock];
    }

    public function save(AuthenticatedPrincipal $actor, string $resource, ?string $id, array $input, string $correlation): array
    {
        $this->authorize($actor, true);
        $input['valid_from'] = null; $input['valid_to'] = null;
        return DB::transaction(function () use ($actor, $resource, $id, $input, $correlation): array {
            [$table, $versions, $identityId, $versionId] = CurrentCatalog::MAP[$resource];
            $fingerprint = CurrentCatalog::fingerprint($input);
            $before = null;
            if ($resource === 'commitment-schedules') {
                foreach ($input['scopes'] ?? [] as $scope) if ($scope['scope_type'] === 'NODE' && !DB::table('nodes')->where(['node_id' => $scope['node_id'], 'hq_id' => $actor->hqId, 'status' => 'ACTIVE'])->exists())
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'The schedule scope is outside the current tenant.');
            }
            if ($resource === 'offerings') {
                foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $dependency)
                    $input[$field] = CurrentCatalog::resolve($dependency, (string) $input[$field], (string) $actor->hqId)[$field];
                foreach ($input['option_rules'] ?? [] as $index => $rule)
                    $input['option_rules'][$index]['service_option_version_id'] = CurrentCatalog::resolve('options', (string) $rule['service_option_version_id'], (string) $actor->hqId)['service_option_version_id'];
                if (!empty($input['commitment_binding']))
                    $input['commitment_binding']['commitment_schedule_version_id'] = CurrentCatalog::resolve('commitment-schedules', (string) $input['commitment_binding']['commitment_schedule_version_id'], (string) $actor->hqId)['commitment_schedule_version_id'];
            }
            if ($id !== null) {
                $identity = DB::table($table)->where([$identityId => $id, 'hq_id' => $actor->hqId])->lockForUpdate()->first();
                if ($identity === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
                if ($identity->saved_input_fingerprint === $fingerprint) return $this->detail($actor, $resource, $id);
                if ((int) $identity->edit_lock !== (int) ($input['expected_version'] ?? 0)) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The record changed since it was loaded.');
                $before = $this->detail($actor, $resource, $id);
                $previous = (array) DB::table($versions)->where($identityId, $id)->orderByDesc('version_number')->first();
                $newId = (string) Str::uuid();
                $copy = $previous;
                $copy[$versionId] = $newId;
                $copy['previous_version_id'] = $previous[$versionId];
                $copy['version_number'] = (int) $previous['version_number'] + 1;
                $copy['status'] = 'DRAFT'; $copy['lock_version'] = 1;
                foreach (['approved_by', 'approved_at', 'published_by', 'published_at', 'content_digest'] as $field) $copy[$field] = null;
                $copy['created_by'] = $actor->userId; $copy['created_at'] = now(); $copy['updated_at'] = now();
                DB::table($versions)->insert($copy);
                $detail = $resource === 'commitment-schedules'
                    ? $this->schedules->update($actor, $newId, [...$input, 'expected_version' => 1], $correlation)
                    : $this->catalog->updateDraft($actor, $resource, $newId, 1, $input, $correlation);
            } else {
                $detail = $resource === 'commitment-schedules' ? $this->schedules->create($actor, $input, $correlation) : $this->catalog->createIdentity($actor, $resource, $input, $correlation);
                $id = (string) $detail[$identityId];
                $newId = (string) $detail[$versionId];
            }
            $validation = $resource === 'commitment-schedules' ? $this->schedules->validate($actor, $newId, true) : $this->catalog->validateDraft($actor, $resource, $newId, true);
            if (!$validation['valid']) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Catalog validation failed.', details: $validation);
            DB::table($versions)->where($identityId, $id)->where('status', 'PUBLISHED')->update(['status' => 'SUPERSEDED', 'updated_at' => now()]);
            DB::table($versions)->where($versionId, $newId)->update(['status' => 'PUBLISHED', 'published_by' => $actor->userId, 'published_at' => now(), 'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR))]);
            DB::table($table)->where($identityId, $id)->update(['edit_lock' => DB::raw('edit_lock + 1'), 'saved_input_fingerprint' => $fingerprint, 'updated_at' => now()]);
            $after = $this->detail($actor, $resource, $id);
            $this->record($actor, $id, $correlation, 'SERVICE_CATALOG_RECORD_SAVED', $before, $after);
            return $after;
        });
    }

    public function setActive(AuthenticatedPrincipal $actor, string $resource, string $id, bool $active, int $expected, string $correlation): array
    {
        $this->authorize($actor, true);
        return DB::transaction(function () use ($actor, $resource, $id, $active, $expected, $correlation): array {
            [$table, , $identityId] = CurrentCatalog::MAP[$resource];
            $identity = DB::table($table)->where([$identityId => $id, 'hq_id' => $actor->hqId])->lockForUpdate()->first();
            if ($identity === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            $before = $this->detail($actor, $resource, $id);
            $status = $active ? 'ACTIVE' : 'INACTIVE';
            if ($identity->status === $status) return $before;
            if ((int) $identity->edit_lock !== $expected) throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The record changed since it was loaded.');
            DB::table($table)->where($identityId, $id)->update(['status' => $status, 'edit_lock' => DB::raw('edit_lock + 1'), 'updated_at' => now()]);
            if ($active) CurrentCatalog::resolve($resource, $id, (string) $actor->hqId);
            $after = $this->detail($actor, $resource, $id);
            $this->record($actor, $id, $correlation, 'SERVICE_CATALOG_STATUS_CHANGED', $before, $after);
            return $after;
        });
    }

    private function record(AuthenticatedPrincipal $actor, string $id, string $correlation, string $action, ?array $before, array $after): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, 'SERVICE_CATALOG_RECORD', $id, $correlation, before: $before, after: $after, sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'SERVICE_CATALOG_RECORD', $id, 'service.catalog.changed', $correlation, ['action' => $action, 'target_type' => 'SERVICE_CATALOG_RECORD', 'target_id' => $id]);
    }
}
