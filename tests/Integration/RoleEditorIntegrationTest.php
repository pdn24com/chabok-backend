<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authorization\Application\AuthorizationCatalog;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;

final class RoleEditorIntegrationTest extends MySqlRedisTestCase
{
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

    public function test_create_edit_clear_and_refresh_round_trip_is_atomic_and_audited(): void
    {
        [$tenant, , $token] = $this->manager();
        $input = ['role_code' => 'custom.editor', 'role_title' => 'نقش آزمایشی', 'permission_codes' => ['consignment.view', 'manifest.view']];
        $key = (string) Str::uuid();
        $created = $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/iam/roles', $input)
            ->assertCreated()->assertJsonPath('data.hq_id', $tenant['hq_id'])->assertJsonPath('data.role_kind', 'CUSTOM');
        $id = $created->json('data.role_id');
        $this->withToken($token)->postJson('/api/v1/iam/roles', $input)->assertCreated()->assertJsonPath('data.role_id', $id);
        $this->assertSame(1, DB::table('roles')->where('role_code', 'custom.editor')->count());
        $this->withToken($token)->postJson('/api/v1/iam/roles', [...$input, 'role_title' => 'Different'])->assertConflict();
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'ویرایش', 'permission_codes' => ['manifest.view']])
            ->assertOk()->assertJsonPath('data.permission_codes', ['manifest.view']);
        $this->withToken($token)->getJson('/api/v1/iam/roles/'.$id)->assertOk()->assertJsonPath('data.role_title', 'ویرایش');
        // A forbidden grant must not save even the otherwise valid title.
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'Must not save', 'permission_codes' => ['manifest.approve']])->assertForbidden();
        $this->assertDatabaseHas('roles', ['role_id' => $id, 'role_title' => 'ویرایش']);
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$id, ['permission_codes' => [], 'status' => 'INACTIVE'])->assertOk()->assertJsonPath('data.permission_codes', []);
        $this->withToken($token)->getJson('/api/v1/iam/roles')->assertOk()->assertJsonFragment(['role_id' => $id]);
        $this->assertDatabaseHas('audit_events', ['action_key' => 'ROLE_CREATED', 'target_id' => $id]);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'iam.role.created']);
        $this->assertSame(0, DB::table('user_role_assignments')->where('role_id', $id)->count());
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
        $created = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/iam/roles', $input)->assertCreated();
        $id = $created->json('data.role_id');
        DB::table('tenant_module_entitlements')->where('hq_id', $tenant['hq_id'])->where('module_code', 'Manifest')->update(['status' => 'DISABLED']);
        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/iam/roles', [...$input, 'role_code' => 'disabled.module'])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/iam/permissions')->assertOk()->assertJsonFragment(['permission_code' => 'manifest.view', 'can_grant' => false]);
        $system = DB::table('roles')->where('role_code', 'branch_manager')->value('role_id');
        $this->withToken($token)->patchJson('/api/v1/iam/roles/'.$system, ['role_title' => 'Forbidden'])->assertUnprocessable();
        [, , $otherToken] = $this->manager('OTHER');
        $this->withToken($otherToken)->getJson('/api/v1/iam/roles/'.$id)->assertForbidden();
        $this->withToken($otherToken)->patchJson('/api/v1/iam/roles/'.$id, ['role_title' => 'Cross tenant'])->assertForbidden();
        $this->withToken($otherToken)->putJson('/api/v1/iam/roles/'.$id.'/permissions', ['permission_codes' => []])->assertForbidden();
        // A management permission in a node must not combine with another role's tenant scope.
        DB::table('user_role_assignments')->where('user_id', $actor['user_id'])->where('role_id', DB::table('roles')->where('role_code', 'hq_admin')->value('role_id'))
            ->update(['scope_type' => 'NODE', 'scope_id' => (string) Str::uuid()]);
        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/iam/roles', [...$input, 'permission_codes' => []])->assertForbidden();
    }
    private function entitlement(string $hqId, string $module, string $status = 'ENABLED'): void
    {
        DB::table('tenant_module_entitlements')->insert([
            'entitlement_id' => (string) Str::uuid(),
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
        return (string) DB::table('roles')->where('role_code', $code)->value('role_id');
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
        $id = (string) Str::uuid();
        DB::table('user_role_assignments')->insert([
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
