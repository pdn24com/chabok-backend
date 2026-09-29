<?php

declare(strict_types=1);

namespace Tests\Integration;

use Modules\Authorization\Application\Catalogs\AuthorizationCatalog;
use Modules\Authorization\Application\Services\RoleNavigation;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Tests\Support\RecordFixtureQuery;

final class RoleEditorIntegrationTest extends MySqlRedisTestCase
{
    public function test_create_edit_clear_and_refresh_round_trip_is_atomic_and_audited(): void
    {
        [$tenant, , $token] = $this->manager();
        $input = ['role_code' => 'custom.editor', 'role_title' => 'نقش آزمایشی', 'permission_codes' => ['consignment.view', 'manifest.view']];
        $key = (string) random_int(1, 2000000000);
        $created = $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/iam/roles', $input)
            ->assertCreated()->assertJsonPath('data.hq_id', $tenant['hq_id'])->assertJsonPath('data.role_kind', 'CUSTOM');
        $id = $created->json('data.role_id');
        $this->withToken($token)->postJson('/api/v1/iam/roles', $input)->assertCreated()->assertJsonPath('data.role_id', $id);
        $this->assertSame(1, RecordFixtureQuery::table('roles')->where('role_code', 'custom.editor')->count());
        $this->withToken($token)->postJson('/api/v1/iam/roles', [...$input, 'role_title' => 'Different'])->assertConflict();
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'ویرایش', 'permission_codes' => ['manifest.view']])
            ->assertOk()->assertJsonPath('data.permission_codes', ['manifest.view']);
        $this->withToken($token)->getJson('/api/v1/iam/roles/'.$id)->assertOk()->assertJsonPath('data.role_title', 'ویرایش');
        // A forbidden grant must not save even the otherwise valid title.
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'Must not save', 'permission_codes' => ['manifest.approve']])->assertForbidden();
        $this->assertDatabaseHasPublic('roles', ['role_id' => $id, 'role_title' => 'ویرایش']);
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['permission_codes' => [], 'status' => 'INACTIVE'])->assertOk()->assertJsonPath('data.permission_codes', []);
        $this->withToken($token)->getJson('/api/v1/iam/roles')->assertOk()->assertJsonFragment(['role_id' => $id]);
        $this->assertDatabaseHasPublic('audit_events', ['action_key' => 'ROLE_CREATED', 'target_id' => $id]);
        $this->assertDatabaseHasPublic('outbox_events', ['event_type' => 'iam.role.created']);
        $this->assertSame(0, RecordFixtureQuery::table('user_role_assignments')->where('role_id', $id)->count());
    }

    public function test_catalog_is_complete_but_grants_obey_entitlement_tenant_scope_and_system_protection(): void
    {
        [$tenant, $actor, $token] = $this->manager();
        $catalog = $this->withToken($token)->getJson('/api/v1/iam/permissions')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(array_keys(AuthorizationCatalog::permissions()), array_column($catalog, 'permission_code'));
        $byCode = array_column($catalog, null, 'permission_code');
        $this->assertTrue($byCode['consignment.view']['can_grant']);
        $this->assertFalse($byCode['manifest.approve']['can_grant']);
        $input = ['role_code' => 'protected.test', 'role_title' => 'Test', 'permission_codes' => ['manifest.view']];
        $created = $this->withToken($token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/roles', $input)->assertCreated();
        $id = $created->json('data.role_id');
        RecordFixtureQuery::table('tenant_module_entitlements')->where('hq_id', $tenant['hq_id'])->where('module_code', 'Manifest')->update(['status' => 'DISABLED']);
        $this->withToken($token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/roles', [...$input, 'role_code' => 'disabled.module'])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/iam/permissions')->assertOk()->assertJsonFragment(['permission_code' => 'manifest.view', 'can_grant' => false]);
        $system = RecordFixtureQuery::table('roles')->where('role_code', 'branch_manager')->value('role_id');
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$system, ['role_title' => 'Forbidden'])->assertUnprocessable();
        [, , $otherToken] = $this->manager('OTHER');
        $this->withToken($otherToken)->getJson('/api/v1/iam/roles/'.$id)->assertForbidden();
        $this->withToken($otherToken)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'Cross tenant'])->assertForbidden();
        $this->withToken($otherToken)->putJson('/api/v1/iam/roles/'.$id.'/permissions', ['permission_codes' => []])->assertForbidden();
        // A management permission in a node must not combine with another role's tenant scope.
        $areaId = (string) random_int(1, 2000000000);
        $nodeId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('areas')->insert(['area_id' => $areaId, 'hq_id' => $tenant['hq_id'], 'area_code' => 'ROLE-SCOPE', 'area_title' => 'Role scope', 'status' => 'ACTIVE']);
        RecordFixtureQuery::table('nodes')->insert(['node_id' => $nodeId, 'hq_id' => $tenant['hq_id'], 'area_id' => $areaId, 'node_code' => 'ROLE-SCOPE', 'node_title' => 'Role scope', 'node_type' => 'BRANCH', 'status' => 'ACTIVE']);

        RecordFixtureQuery::table('user_role_assignments')->where('user_id', $actor['user_id'])->where('role_id', RecordFixtureQuery::table('roles')->where('role_code', 'hq_admin')->value('role_id'))
            ->update(['scope_type' => 'NODE', 'scope_id' => $nodeId]);
        $this->withToken($token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/roles', [...$input, 'permission_codes' => []])->assertForbidden();
    }

    public function test_menu_preferences_round_trip_clone_and_failure_rollback(): void
    {
        [$tenant, , $token] = $this->manager('MENUS');
        $input = ['role_code' => 'menus.custom', 'role_title' => 'Menus', 'permission_codes' => ['consignment.view'], 'menu_keys' => ['profile', 'consignments']];
        $key = (string) random_int(1, 2000000000);
        $id = $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/iam/roles', $input)
            ->assertCreated()->assertJsonPath('data.menu_keys', ['consignments', 'profile'])->json('data.role_id');
        $this->withToken($token)->postJson('/api/v1/iam/roles', $input)->assertCreated()->assertJsonPath('data.role_id', $id);
        $this->withToken($token)->postJson('/api/v1/iam/roles', [...$input, 'menu_keys' => []])->assertConflict();
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'Renamed'])->assertOk()->assertJsonPath('data.menu_keys', ['consignments', 'profile']);
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'Must roll back', 'permission_codes' => ['manifest.approve'], 'menu_keys' => []])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/iam/roles/'.$id)->assertOk()->assertJsonPath('data.role_title', 'Renamed')->assertJsonPath('data.menu_keys', ['consignments', 'profile']);
        foreach ([['unknown-menu'], ['profile', 'profile'], ['pricing']] as $invalid) {
            $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['menu_keys' => $invalid])->assertUnprocessable();
        }
        $clone = $this->withToken($token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/roles/'.$id.'/clone', ['role_code' => 'menus.copy', 'role_title' => 'Copy'])
            ->assertCreated()->assertJsonPath('data.menu_keys', ['consignments', 'profile'])->json('data.role_id');
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$clone, ['menu_keys' => []])->assertOk()->assertJsonPath('data.menu_keys', []);
        $this->assertTrue(RecordFixtureQuery::table('role_menu_preferences')->where('role_id', $clone)->exists());
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$clone, ['menu_keys' => null])->assertOk()->assertJsonPath('data.menu_keys', null);
        $this->assertFalse(RecordFixtureQuery::table('role_menu_preferences')->where('role_id', $clone)->exists());
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$this->roleId('branch_manager'), ['menu_keys' => []])->assertUnprocessable();
        [, , $other] = $this->manager('MENUS-OTHER');
        $this->withToken($other)->patchJson('/api/v1/iam/roles/'.$id, ['menu_keys' => []])->assertForbidden();
        $this->assertSame(['consignments', 'profile'], app(RoleNavigation::class)->forRole($id));
        $this->assertTrue(RecordFixtureQuery::table('audit_events')->where('target_id', $id)->exists());
    }

    public function test_context_combines_active_menu_selections_without_granting_permissions(): void
    {
        [$tenant, , $token] = $this->manager('NAV-CONTEXT');
        $target = $this->user($tenant['hq_id'], 'menu-reader');
        $input = ['role_code' => 'menus.reader', 'role_title' => 'Reader', 'permission_codes' => ['branch_panel.access', 'node_context.view'], 'menu_keys' => ['roles', 'profile']];
        $id = $this->withToken($token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/roles', $input)->assertCreated()->json('data.role_id');
        $this->withToken($token)->postJson('/api/v1/iam/users/'.$target['user_id'].'/role-assignments', ['assignments' => [['role_id' => $id, 'scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false]]])->assertCreated();
        $reader = $this->login('menu-reader')['token'];
        $this->withToken($reader)->getJson('/api/v1/me/context')->assertOk()->assertJsonPath('data.menu_keys', ['profile', 'roles']);
        $this->withToken($reader)->getJson('/api/v1/iam/roles')->assertForbidden();
        $this->withToken($reader)->patchJson('/api/v1/iam/roles/'.$id, ['menu_keys' => ['dashboard']])->assertForbidden();
        // Editing menus invalidates the already-cached context of assigned users.
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['menu_keys' => ['dashboard']])->assertOk();
        $this->withToken($reader)->getJson('/api/v1/me/context')->assertOk()->assertJsonPath('data.menu_keys', ['dashboard']);
        $otherId = $this->withToken($token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/roles', [...$input, 'role_code' => 'menus.second', 'menu_keys' => ['sessions']])->assertCreated()->json('data.role_id');
        $this->withToken($token)->postJson('/api/v1/iam/users/'.$target['user_id'].'/role-assignments', ['assignments' => [['role_id' => $otherId, 'scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false]]])->assertCreated();
        $this->withToken($reader)->getJson('/api/v1/me/context')->assertOk()->assertJsonPath('data.menu_keys', ['dashboard', 'sessions']);
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$otherId, ['status' => 'INACTIVE'])->assertOk();
        $this->withToken($reader)->getJson('/api/v1/me/context')->assertOk()->assertJsonPath('data.menu_keys', ['dashboard']);
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['menu_keys' => []])->assertOk();
        $this->withToken($reader)->getJson('/api/v1/me/context')->assertOk()->assertJsonPath('data.menu_keys', []);
        $this->withToken($token)->postJson('/api/v1/iam/users/'.$target['user_id'].'/role-assignments', ['assignments' => [['role_id' => $this->roleId('branch_read_only'), 'scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false]]])->assertCreated();
        $this->withToken($reader)->getJson('/api/v1/me/context')->assertOk()->assertJsonPath('data.menu_keys', null);
    }

    private function manager(string $code = 'EDITOR'): array
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $tenant = $this->tenant($code);
        $user = $this->user($tenant['hq_id'], strtolower($code));
        $this->enableBranchModules($tenant['hq_id']);
        $this->assignment($tenant['hq_id'], $user['user_id'], 'hq_admin', 'TENANT');
        $this->assignment($tenant['hq_id'], $user['user_id'], 'branch_manager', 'TENANT');

        return [$tenant, $user, $this->login(strtolower($code))['token']];
    }

    private function entitlement(string $hqId, string $module, string $status = 'ENABLED'): void
    {
        RecordFixtureQuery::table('tenant_module_entitlements')->insert([
            'entitlement_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hqId,
            'module_code' => $module,
            'status' => $status,
            'activated_at' => $status === 'ENABLED' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enableBranchModules(string $hqId): void
    {
        foreach ([
            'Foundation', 'IAM', 'Consignment', 'Parcel', 'Manifest',
            'Pickup', 'Exception', 'Driver', 'LiveOperations',
        ] as $module) {
            $this->entitlement($hqId, $module);
        }
    }

    private function roleId(string $code): string
    {
        return (string) RecordFixtureQuery::table('roles')->where('role_code', $code)->value('role_id');
    }

    private function assignment(
        string $hqId,
        string $userId,
        string $roleCode,
        string $scopeType,
        ?string $scopeId = null,
        bool $descendants = false,
    ): string {
        $roleId = $this->roleId($roleCode);
        $id = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('user_role_assignments')->insert([
            'assignment_id' => $id,
            'hq_id' => $hqId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'includes_descendants' => $descendants,
            'status' => 'ACTIVE',
            'active_slot' => hash('sha256', "{$userId}|{$roleId}|{$scopeType}|".($scopeId ?? '-')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
