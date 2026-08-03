<?php

declare(strict_types=1);

namespace Modules\Authorization\Application;

use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AuthorizationService implements AuthorizationContextResolver
{
    private const CACHE_TTL = 300;

    public function __construct(
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @return array<string, mixed> */
    public function resolve(AuthenticatedPrincipal $principal): array
    {
        $key = $this->cacheKey($principal->userId);
        $cached = Redis::connection('cache')->get($key);
        if (is_string($cached)) {
            return json_decode($cached, true, 512, JSON_THROW_ON_ERROR);
        }

        $user = DB::table('users')->where('user_id', $principal->userId)->first();
        if ($user === null || $user->status !== 'ACTIVE' || $user->hq_id !== $principal->hqId) {
            throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'Authentication required.');
        }

        $assignments = DB::table('user_role_assignments as a')
            ->join('roles as r', 'r.role_id', '=', 'a.role_id')
            ->where('a.user_id', $principal->userId)
            ->where('a.status', 'ACTIVE')
            ->where('r.status', 'ACTIVE')
            ->when(
                $principal->hqId === null,
                fn ($query) => $query->whereNull('a.hq_id'),
                fn ($query) => $query->where('a.hq_id', $principal->hqId),
            )
            ->select([
                'a.assignment_id', 'a.hq_id', 'a.scope_type', 'a.scope_id',
                'a.includes_descendants', 'r.role_id', 'r.role_code',
            ])->get();

        $roleIds = $assignments->pluck('role_id')->unique()->values();
        $permissionRows = $roleIds->isEmpty()
            ? collect()
            : DB::table('role_permissions as rp')
                ->join('permissions as p', 'p.permission_id', '=', 'rp.permission_id')
                ->whereIn('rp.role_id', $roleIds)
                ->where('p.status', 'ACTIVE')
                ->select(['p.permission_code', 'p.module_code'])
                ->distinct()->get();

        $entitlements = $principal->hqId === null
            ? collect()
            : DB::table('tenant_module_entitlements')
                ->where('hq_id', $principal->hqId)
                ->orderBy('module_code')
                ->get(['module_code', 'status']);
        $enabledModules = $entitlements->where('status', 'ENABLED')
            ->pluck('module_code')->map(fn ($value) => strtolower((string) $value))->all();
        $isPlatform = $principal->hqId === null
            && $assignments->contains(
                fn ($row): bool => $row->role_code === 'platform_super_admin'
                    && $row->scope_type === 'PLATFORM'
                    && $row->scope_id === null,
            );

        $permissions = $permissionRows
            ->filter(fn ($row): bool => $isPlatform
                || in_array(strtolower((string) $row->module_code), $enabledModules, true))
            ->pluck('permission_code')->map(fn ($value) => (string) $value)
            ->unique()->sort()->values()->all();
        $scopes = $assignments->map(fn ($row): array => [
            'scope_type' => (string) $row->scope_type,
            'scope_id' => $row->scope_id === null ? null : (string) $row->scope_id,
            'includes_descendants' => (bool) $row->includes_descendants,
        ])->unique(fn (array $scope): string => json_encode($scope, JSON_THROW_ON_ERROR))->values()->all();
        $nodes = $principal->hqId === null ? [] : $this->accessibleNodeIds($principal->hqId, $assignments);

        $context = [
            'hq_id' => $principal->hqId,
            'is_platform_admin' => $isPlatform,
            'role_codes' => $assignments->pluck('role_code')->map(fn ($value) => (string) $value)
                ->unique()->sort()->values()->all(),
            'permissions' => $permissions,
            'scopes' => $scopes,
            'accessible_node_ids' => $nodes,
            'module_entitlements' => $entitlements->map(fn ($row): array => [
                'module_code' => (string) $row->module_code,
                'status' => (string) $row->status,
            ])->values()->all(),
            'default_node_id' => $nodes[0] ?? null,
        ];
        Redis::connection('cache')->setex($key, self::CACHE_TTL, json_encode($context, JSON_THROW_ON_ERROR));

        return $context;
    }

    public function assertPermission(
        AuthenticatedPrincipal $actor,
        string $permissionCode,
        ?string $expectedHqId = null,
    ): void {
        if ($expectedHqId !== null && $actor->hqId !== $expectedHqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $permission = DB::table('permissions')->where('permission_code', $permissionCode)
            ->where('status', 'ACTIVE')->first();
        if ($permission === null) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if ($actor->hqId !== null) {
            $status = DB::table('tenant_module_entitlements')->where([
                'hq_id' => $actor->hqId,
                'module_code' => $permission->module_code,
            ])->value('status');
            if ($status !== 'ENABLED') {
                throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
            }
        }
        if (! in_array($permissionCode, $this->resolve($actor)['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function accessibleNodes(AuthenticatedPrincipal $actor): array
    {
        $this->assertPermission($actor, 'node_context.view', $actor->hqId);
        $nodeIds = $this->resolve($actor)['accessible_node_ids'];
        if ($nodeIds === []) {
            return [];
        }

        return DB::table('nodes')->where('hq_id', $actor->hqId)
            ->whereIn('node_id', $nodeIds)->where('status', 'ACTIVE')
            ->orderBy('node_title')->get([
                'node_id', 'node_code', 'node_title', 'node_type', 'status',
            ])->map(fn ($row): array => (array) $row)->all();
    }

    public function assertNodeAccessible(AuthenticatedPrincipal $actor, string $nodeId): void
    {
        $node = DB::table('nodes')->where('node_id', $nodeId)->first(['hq_id']);
        if ($node !== null && $node->hq_id !== $actor->hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $this->assertPermission($actor, 'node_context.switch', $actor->hqId);
        if (! in_array($nodeId, $this->resolve($actor)['accessible_node_ids'], true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
    }

    public function hasActivePlatformAssignment(string $userId): bool
    {
        return DB::table('users as u')
            ->join('user_role_assignments as a', 'a.user_id', '=', 'u.user_id')
            ->join('roles as r', 'r.role_id', '=', 'a.role_id')
            ->where('u.user_id', $userId)->whereNull('u.hq_id')->where('u.status', 'ACTIVE')
            ->whereNull('a.hq_id')->where('a.status', 'ACTIVE')
            ->where('a.scope_type', 'PLATFORM')->whereNull('a.scope_id')
            ->where('r.role_code', 'platform_super_admin')->where('r.status', 'ACTIVE')
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    public function listRoles(AuthenticatedPrincipal $actor): array
    {
        $this->assertPermission($actor, 'iam.roles.assign', $this->tenantId($actor));

        return DB::table('roles')->where(function ($query) use ($actor): void {
            $query->whereNull('hq_id')->orWhere('hq_id', $actor->hqId);
        })->where('status', 'ACTIVE')->orderBy('role_kind')->orderBy('role_code')->get()
            ->map(fn ($row): array => $this->rolePayload((string) $row->role_id))->all();
    }

    /** @return array<string, mixed> */
    public function getRole(AuthenticatedPrincipal $actor, string $roleId): array
    {
        $this->assertPermission($actor, 'iam.roles.assign', $this->tenantId($actor));
        $this->assertVisibleRole($roleId, $actor->hqId);

        return $this->rolePayload($roleId);
    }

    /** @param array<string, string> $input */
    public function updateRole(
        AuthenticatedPrincipal $actor,
        string $roleId,
        array $input,
        string $correlationId,
    ): array {
        $hqId = $this->tenantId($actor);
        $this->assertPermission($actor, 'iam.roles.manage', $hqId);

        return $this->transactions->run(function () use ($actor, $roleId, $input, $correlationId, $hqId): array {
            $role = DB::table('roles')->where('role_id', $roleId)->lockForUpdate()->first();
            $this->assertMutableRole($role, $hqId);
            $before = $this->rolePayload($roleId);
            DB::table('roles')->where('role_id', $roleId)->update([
                ...$input,
                'updated_at' => now(),
            ]);
            $after = $this->rolePayload($roleId);
            $this->invalidateRoleUsers($roleId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_UPDATED', 'ROLE', $roleId, $correlationId, $before, $after);
            $this->outbox->write($hqId, 'ROLE', $roleId, 'iam.role.updated', $correlationId, [
                'role_id' => $roleId,
            ]);

            return $after;
        });
    }

    /** @param array{role_code: string, role_title: string, description?: string|null} $input */
    public function cloneRole(
        AuthenticatedPrincipal $actor,
        string $sourceRoleId,
        array $input,
        string $correlationId,
    ): array {
        $hqId = $this->tenantId($actor);
        $this->assertPermission($actor, 'iam.roles.manage', $hqId);

        return $this->transactions->run(function () use ($actor, $sourceRoleId, $input, $correlationId, $hqId): array {
            $source = DB::table('roles')->where('role_id', $sourceRoleId)->lockForUpdate()->first();
            if ($source === null || ! (bool) $source->is_cloneable || $source->status !== 'ACTIVE') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The role cannot be cloned.');
            }
            if ($source->hq_id !== null && $source->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            }
            $sourcePermissions = $this->permissionCodesForRole($sourceRoleId);
            $this->assertDelegablePermissions($actor, $sourcePermissions);
            $roleId = (string) Str::uuid();
            try {
                DB::table('roles')->insert([
                    'role_id' => $roleId,
                    'hq_id' => $hqId,
                    'owner_key' => $hqId,
                    'role_code' => $input['role_code'],
                    'role_title' => $input['role_title'],
                    'description' => $input['description'] ?? null,
                    'role_kind' => 'CUSTOM',
                    'is_cloneable' => true,
                    'status' => 'ACTIVE',
                    'created_by' => $actor->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if ($exception->getCode() === '23000') {
                    throw new ApiException(ApiErrorCode::Conflict, 409, 'The role code is already in use.');
                }
                throw $exception;
            }
            $this->insertRolePermissions($roleId, $sourcePermissions, $actor->userId);
            $payload = $this->rolePayload($roleId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_CLONED', 'ROLE', $roleId, $correlationId, after: $payload);
            $this->outbox->write($hqId, 'ROLE', $roleId, 'iam.role.cloned', $correlationId, [
                'role_id' => $roleId, 'source_role_id' => $sourceRoleId,
            ]);

            return $payload;
        });
    }

    /** @param list<string> $permissionCodes */
    public function replaceRolePermissions(
        AuthenticatedPrincipal $actor,
        string $roleId,
        array $permissionCodes,
        string $correlationId,
    ): array {
        $hqId = $this->tenantId($actor);
        $this->assertPermission($actor, 'iam.roles.manage', $hqId);
        $this->assertDelegablePermissions($actor, $permissionCodes);

        return $this->transactions->run(function () use ($actor, $roleId, $permissionCodes, $correlationId, $hqId): array {
            $role = DB::table('roles')->where('role_id', $roleId)->lockForUpdate()->first();
            $this->assertMutableRole($role, $hqId);
            $before = $this->rolePayload($roleId);
            DB::table('role_permissions')->where('role_id', $roleId)->delete();
            $this->insertRolePermissions($roleId, $permissionCodes, $actor->userId);
            $after = $this->rolePayload($roleId);
            $this->invalidateRoleUsers($roleId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_PERMISSIONS_REPLACED', 'ROLE', $roleId, $correlationId, $before, $after);
            $this->outbox->write($hqId, 'ROLE', $roleId, 'iam.role.permissions_replaced', $correlationId, [
                'role_id' => $roleId,
            ]);

            return $after;
        });
    }

    /** @return list<array<string, mixed>> */
    public function listPermissions(AuthenticatedPrincipal $actor, ?string $moduleCode): array
    {
        $this->assertPermission($actor, 'iam.roles.assign', $this->tenantId($actor));

        return DB::table('permissions')->where('status', 'ACTIVE')
            ->when($moduleCode !== null, fn ($query) => $query->where('module_code', $moduleCode))
            ->orderBy('permission_code')->get([
                'permission_id', 'permission_code', 'module_code', 'description', 'status',
            ])->map(fn ($row): array => (array) $row)->all();
    }

    /** @param list<array<string, mixed>> $assignments
     *  @return list<array<string, mixed>>
     */
    public function createAssignments(
        AuthenticatedPrincipal $actor,
        string $userId,
        array $assignments,
        string $correlationId,
    ): array {
        $hqId = $this->tenantId($actor);
        $this->assertPermission($actor, 'iam.roles.assign', $hqId);

        return $this->transactions->run(function () use ($actor, $userId, $assignments, $correlationId, $hqId): array {
            $user = DB::table('users')->where('user_id', $userId)->lockForUpdate()->first();
            if ($user === null || $user->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            }
            $created = [];
            foreach ($assignments as $input) {
                $created[] = $this->createTenantAssignment($actor, $userId, $hqId, $input);
            }
            $this->invalidateUser($userId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_ASSIGNMENTS_CREATED', 'USER', $userId, $correlationId, after: [
                'assignment_ids' => array_column($created, 'assignment_id'),
            ]);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.assignments_changed', $correlationId, [
                'user_id' => $userId,
            ]);

            return $created;
        });
    }

    public function revokeAssignment(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $assignmentId,
        string $correlationId,
    ): void {
        $hqId = $this->tenantId($actor);
        $this->assertPermission($actor, 'iam.roles.assign', $hqId);
        $this->transactions->run(function () use ($actor, $userId, $assignmentId, $correlationId, $hqId): void {
            $assignment = DB::table('user_role_assignments')->where([
                'assignment_id' => $assignmentId,
                'user_id' => $userId,
            ])->lockForUpdate()->first();
            if ($assignment === null || $assignment->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            }
            if ($assignment->status !== 'ACTIVE') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The assignment is not active.');
            }
            $this->assertScopeDelegable($actor, (string) $assignment->scope_type, $assignment->scope_id);
            DB::table('user_role_assignments')->where('assignment_id', $assignmentId)->update([
                'status' => 'REVOKED',
                'active_slot' => null,
                'revoked_by' => $actor->userId,
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
            $this->invalidateUser($userId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_ASSIGNMENT_REVOKED', 'ASSIGNMENT', $assignmentId, $correlationId);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.assignments_changed', $correlationId, [
                'user_id' => $userId,
            ]);
        });
    }

    /** @return list<array{module_code: string, status: string}> */
    public function listEntitlements(
        AuthenticatedPrincipal $actor,
        string $correlationId,
    ): array
    {
        if ($actor->hqId === null) {
            $this->assertPermission($actor, 'iam.entitlements.view');
            $rows = DB::table('tenant_module_entitlements')->orderBy('hq_id')->orderBy('module_code')
                ->get(['module_code', 'status'])->map(fn ($row): array => (array) $row)->all();
            $this->audit->write(
                null,
                $actor->userId,
                'ENTITLEMENTS_VIEWED',
                'PLATFORM',
                null,
                $correlationId,
            );

            return $rows;
        }
        $this->assertPermission($actor, 'iam.entitlements.view', $actor->hqId);
        $rows = DB::table('tenant_module_entitlements')->where('hq_id', $actor->hqId)
            ->orderBy('module_code')->get(['module_code', 'status'])
            ->map(fn ($row): array => (array) $row)->all();
        $this->audit->write(
            $actor->hqId,
            $actor->userId,
            'ENTITLEMENTS_VIEWED',
            'HQ_TENANT',
            $actor->hqId,
            $correlationId,
        );

        return $rows;
    }

    public function assignInitial(
        string $hqId,
        string $userId,
        string $actorId,
        array $assignments,
        string $correlationId,
    ): void {
        $actor = DB::table('users')->where('user_id', $actorId)->first();
        if ($actor === null || $actor->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $principal = new AuthenticatedPrincipal($actorId, '', $hqId, false);
        $this->createAssignments($principal, $userId, $assignments, $correlationId);
    }

    public function invalidateUser(string $userId): void
    {
        Redis::connection('cache')->del($this->cacheKey($userId));
    }

    public function invalidateTenant(string $hqId): void
    {
        DB::table('users')->where('hq_id', $hqId)->pluck('user_id')
            ->each(fn ($userId) => $this->invalidateUser((string) $userId));
    }

    private function tenantId(AuthenticatedPrincipal $actor): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }

        return $actor->hqId;
    }

    /** @param Collection<int, object> $assignments
     *  @return list<string>
     */
    private function accessibleNodeIds(string $hqId, Collection $assignments): array
    {
        $all = false;
        $areaIds = [];
        $nodeIds = [];
        foreach ($assignments as $assignment) {
            if ($assignment->scope_type === 'TENANT') {
                $all = true;
            } elseif ($assignment->scope_type === 'NODE' && $assignment->scope_id !== null) {
                $nodeIds[] = (string) $assignment->scope_id;
            } elseif ($assignment->scope_type === 'AREA' && $assignment->scope_id !== null) {
                $areaIds[] = (string) $assignment->scope_id;
                if ((bool) $assignment->includes_descendants) {
                    $areaIds = [...$areaIds, ...$this->descendantAreaIds($hqId, (string) $assignment->scope_id)];
                }
            }
        }
        $query = DB::table('nodes')->where('hq_id', $hqId)->where('status', 'ACTIVE');
        if (! $all) {
            if ($areaIds === [] && $nodeIds === []) {
                return [];
            }
            $query->where(function ($query) use ($areaIds, $nodeIds): void {
                if ($areaIds !== []) {
                    $query->whereIn('area_id', array_values(array_unique($areaIds)));
                }
                if ($nodeIds !== []) {
                    $areaIds === [] ? $query->whereIn('node_id', $nodeIds) : $query->orWhereIn('node_id', $nodeIds);
                }
            });
        }

        return $query->orderBy('node_id')->pluck('node_id')->map(fn ($id) => (string) $id)->all();
    }

    /** @return list<string> */
    private function descendantAreaIds(string $hqId, string $areaId): array
    {
        return array_map(
            static fn ($row): string => (string) $row->area_id,
            DB::select(
                <<<'SQL'
                WITH RECURSIVE descendants AS (
                    SELECT child_area_id AS area_id FROM area_hierarchies
                    WHERE hq_id = ? AND parent_area_id = ?
                    UNION ALL
                    SELECT h.child_area_id FROM area_hierarchies h
                    JOIN descendants d ON h.parent_area_id = d.area_id
                    WHERE h.hq_id = ?
                )
                SELECT DISTINCT area_id FROM descendants
                SQL,
                [$hqId, $areaId, $hqId],
            ),
        );
    }

    /** @return array<string, mixed> */
    private function rolePayload(string $roleId): array
    {
        $role = DB::table('roles')->where('role_id', $roleId)->first();
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }

        return [
            'role_id' => (string) $role->role_id,
            'hq_id' => $role->hq_id === null ? null : (string) $role->hq_id,
            'role_code' => (string) $role->role_code,
            'role_title' => (string) $role->role_title,
            'description' => $role->description === null ? null : (string) $role->description,
            'role_kind' => (string) $role->role_kind,
            'is_cloneable' => (bool) $role->is_cloneable,
            'status' => (string) $role->status,
            'permission_codes' => $this->permissionCodesForRole($roleId),
        ];
    }

    /** @return list<string> */
    private function permissionCodesForRole(string $roleId): array
    {
        return DB::table('role_permissions as rp')
            ->join('permissions as p', 'p.permission_id', '=', 'rp.permission_id')
            ->where('rp.role_id', $roleId)->where('p.status', 'ACTIVE')
            ->orderBy('p.permission_code')->pluck('p.permission_code')
            ->map(fn ($code) => (string) $code)->all();
    }

    private function assertVisibleRole(string $roleId, ?string $hqId): void
    {
        $role = DB::table('roles')->where('role_id', $roleId)->first();
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if ($role->hq_id !== null && $role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
    }

    private function assertMutableRole(?object $role, string $hqId): void
    {
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if ($role->role_kind !== 'CUSTOM') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'System and template roles are immutable.');
        }
        if ($role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
    }

    /** @param list<string> $permissionCodes */
    private function assertDelegablePermissions(AuthenticatedPrincipal $actor, array $permissionCodes): void
    {
        $approved = array_keys(AuthorizationCatalog::permissions());
        if (array_diff($permissionCodes, $approved) !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An unknown permission was supplied.');
        }
        $effective = $this->resolve($actor)['permissions'];
        $assigned = DB::table('user_role_assignments as ura')
            ->join('roles as r', 'r.role_id', '=', 'ura.role_id')
            ->join('role_permissions as rp', 'rp.role_id', '=', 'r.role_id')
            ->join('permissions as p', 'p.permission_id', '=', 'rp.permission_id')
            ->where('ura.user_id', $actor->userId)
            ->where('ura.status', 'ACTIVE')
            ->where('r.status', 'ACTIVE')
            ->where('p.status', 'ACTIVE')
            ->pluck('p.permission_code')
            ->map(static fn ($code): string => (string) $code)
            ->all();
        if (array_diff($permissionCodes, array_values(array_unique([...$effective, ...$assigned]))) !== []) {
            throw new ApiException(ApiErrorCode::DelegationDenied, 403, 'Access denied.');
        }
    }

    /** @param list<string> $permissionCodes */
    private function insertRolePermissions(string $roleId, array $permissionCodes, string $actorId): void
    {
        if ($permissionCodes === []) {
            return;
        }
        $permissions = DB::table('permissions')->whereIn('permission_code', $permissionCodes)
            ->where('status', 'ACTIVE')->get(['permission_id', 'permission_code']);
        if ($permissions->count() !== count(array_unique($permissionCodes))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An unknown permission was supplied.');
        }
        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_permission_id' => (string) Str::uuid(),
                'role_id' => $roleId,
                'permission_id' => $permission->permission_id,
                'created_by' => $actorId,
                'created_at' => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function createTenantAssignment(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $hqId,
        array $input,
    ): array {
        $role = DB::table('roles')->where('role_id', $input['role_id'])->where('status', 'ACTIVE')->first();
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The role is invalid.');
        }
        if ($role->hq_id !== null && $role->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        if ($role->role_code === 'platform_super_admin' || $input['scope_type'] === 'PLATFORM') {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $permissionCodes = $this->permissionCodesForRole((string) $role->role_id);
        $this->assertDelegablePermissions($actor, $permissionCodes);
        $scopeId = $input['scope_id'] ?? null;
        $this->assertScopeTarget($hqId, $userId, (string) $input['scope_type'], $scopeId, (bool) $input['includes_descendants']);
        $this->assertScopeDelegable($actor, (string) $input['scope_type'], $scopeId);
        $slot = hash('sha256', implode('|', [
            $userId, (string) $role->role_id, (string) $input['scope_type'], (string) ($scopeId ?? '-'),
        ]));
        if (DB::table('user_role_assignments')->where('active_slot', $slot)->exists()) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'The assignment already exists.');
        }
        $assignmentId = (string) Str::uuid();
        try {
            DB::table('user_role_assignments')->insert([
                'assignment_id' => $assignmentId,
                'hq_id' => $hqId,
                'user_id' => $userId,
                'role_id' => $role->role_id,
                'scope_type' => $input['scope_type'],
                'scope_id' => $scopeId,
                'includes_descendants' => (bool) $input['includes_descendants'],
                'status' => 'ACTIVE',
                'active_slot' => $slot,
                'assigned_by' => $actor->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'The assignment already exists.');
            }
            throw $exception;
        }

        return [
            'assignment_id' => $assignmentId,
            'user_id' => $userId,
            'role_id' => (string) $role->role_id,
            'scope_type' => (string) $input['scope_type'],
            'scope_id' => $scopeId,
            'includes_descendants' => (bool) $input['includes_descendants'],
            'status' => 'ACTIVE',
        ];
    }

    private function assertScopeTarget(
        string $hqId,
        string $userId,
        string $scopeType,
        ?string $scopeId,
        bool $includesDescendants,
    ): void {
        if ($scopeType === 'TENANT') {
            if ($scopeId !== null || $includesDescendants) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Tenant scope cannot have a target or descendants flag.');
            }
            return;
        }
        if ($scopeType === 'SELF') {
            if ($scopeId !== $userId || $includesDescendants) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Self scope must target the assigned user.');
            }
            return;
        }
        if ($scopeType === 'AREA') {
            if ($scopeId === null || ! DB::table('areas')->where(['hq_id' => $hqId, 'area_id' => $scopeId])->exists()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The area scope is invalid.');
            }
            return;
        }
        if ($scopeType === 'NODE') {
            if ($scopeId === null || $includesDescendants
                || ! DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $scopeId])->exists()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The node scope is invalid.');
            }
            return;
        }
        if (in_array($scopeType, ['VENDOR', 'VENDOR_BRANCH'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The scope target is not available in Stage 0.');
        }
        throw new ApiException(ApiErrorCode::ValidationError, 422, 'The scope type is invalid.');
    }

    private function assertScopeDelegable(
        AuthenticatedPrincipal $actor,
        string $scopeType,
        ?string $scopeId,
    ): void {
        $scopes = $this->resolve($actor)['scopes'];
        foreach ($scopes as $scope) {
            if ($scope['scope_type'] === 'TENANT') {
                return;
            }
            if ($scopeType === $scope['scope_type'] && $scopeId === $scope['scope_id']) {
                return;
            }
            if ($scopeType === 'AREA' && $scope['scope_type'] === 'AREA' && $scope['includes_descendants']) {
                $areas = $this->descendantAreaIds((string) $actor->hqId, (string) $scope['scope_id']);
                if (in_array((string) $scopeId, $areas, true)) {
                    return;
                }
            }
            if ($scopeType === 'NODE' && $scope['scope_type'] === 'AREA' && $scope['includes_descendants']) {
                $node = DB::table('nodes')->where('node_id', $scopeId)->first(['hq_id', 'area_id']);
                if ($node !== null && $node->hq_id === $actor->hqId) {
                    $areas = [(string) $scope['scope_id'], ...$this->descendantAreaIds((string) $actor->hqId, (string) $scope['scope_id'])];
                    if (in_array((string) $node->area_id, $areas, true)) {
                        return;
                    }
                }
            }
        }
        throw new ApiException(ApiErrorCode::DelegationDenied, 403, 'Access denied.');
    }

    private function invalidateRoleUsers(string $roleId): void
    {
        DB::table('user_role_assignments')->where('role_id', $roleId)->where('status', 'ACTIVE')
            ->pluck('user_id')->each(fn ($userId) => $this->invalidateUser((string) $userId));
    }

    private function cacheKey(string $userId): string
    {
        return "chabok:authz:effective:{$userId}";
    }
}
