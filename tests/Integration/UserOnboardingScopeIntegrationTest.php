<?php

declare(strict_types=1);

namespace Tests\Integration;

use Modules\Authorization\Application\Services\AuthorizationCacheInvalidator;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Foundation\Application\Services\ScopedAccess;
use Tests\Support\AccessContexts;
use Tests\Support\RecordFixtureQuery;

final class UserOnboardingScopeIntegrationTest extends MySqlRedisTestCase
{
    private string $hq;

    private string $token;

    private string $admin;

    private string $role;

    private string $root;

    private string $child;

    private string $leaf;

    private string $first;

    private string $second;

    private string $deep;

    public function test_named_options_and_exact_area_delegation_cannot_escalate_to_descendants(): void
    {
        $all = $this->withToken($this->token)->getJson('/api/v1/iam/assignment-options')->assertOk()->json('data');
        $this->assertTrue($all['tenant_allowed']);
        $this->assertCount(3, $all['areas']);
        $this->assertContains('ROOT / CHILD / LEAF', array_column($all['areas'], 'path'));
        $limited = $this->user($this->hq, 'limited');
        $manager = $this->role('manager', ['iam.users.view', 'iam.users.manage', 'iam.roles.assign', 'consignment.view']);
        $reader = $this->role('reader', ['consignment.view']);
        $this->assign($limited['user_id'], $manager, 'AREA', $this->root);
        $token = $this->login('limited')['token'];
        $options = $this->withToken($token)->getJson('/api/v1/iam/assignment-options')->assertOk()->json('data');
        $this->assertFalse($options['tenant_allowed']);
        $this->assertCount(1, $options['areas']);
        $this->assertFalse($options['areas'][0]['can_include_descendants']);
        $this->assertCount(2, $options['nodes']);
        $target = $this->user($this->hq, 'target')['user_id'];
        $request = ['role_id' => $reader, 'scope_type' => 'AREA', 'scope_id' => $this->root, 'includes_descendants' => true];
        $url = "/api/v1/iam/users/{$target}/role-assignments";
        $this->withToken($token)->postJson($url, ['assignments' => [$request]])->assertForbidden();
        $this->withToken($token)->postJson($url, ['assignments' => [[...$request, 'scope_type' => 'NODE', 'scope_id' => $this->first, 'includes_descendants' => false]]])->assertCreated();
        $this->withToken($token)->postJson($url, ['assignments' => [[...$request, 'scope_type' => 'NODE', 'scope_id' => $this->deep, 'includes_descendants' => false]]])->assertForbidden();
    }

    public function test_permission_scopes_do_not_multiply_and_grants_cannot_be_laundered(): void
    {
        $person = $this->user($this->hq, 'mixed');
        $read = $this->role('wide-read', ['node_context.view', 'node_context.switch', 'consignment.view', 'iam.roles.assign']);
        $write = $this->role('local-write', ['consignment.edit']);
        $this->assign($person['user_id'], $read, 'AREA', $this->root, true);
        $this->assign($person['user_id'], $write, 'NODE', $this->first);
        $token = $this->login('mixed')['token'];
        $context = $this->withToken($token)->getJson('/api/v1/me/context')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$this->first, $this->second, $this->deep], $this->app->make(ScopedAccess::class)->nodes(AccessContexts::make($context), 'consignment.view'));
        $this->assertSame([$this->first], $this->app->make(ScopedAccess::class)->nodes(AccessContexts::make($context), 'consignment.edit'));
        $this->withToken($token)->withHeader('X-Node-Id', $this->deep)->patchJson('/api/v1/consignments/'.(string) random_int(1, 2000000000), ['expected_version' => 1, 'change_reason' => 'TEST', 'note' => 'Forbidden', 'payer' => 'RECEIVER'])->assertForbidden();
        $target = $this->user($this->hq, 'grant-target')['user_id'];
        $this->withToken($token)->withoutHeader('X-Node-Id')->postJson("/api/v1/iam/users/{$target}/role-assignments", ['assignments' => [['role_id' => $write, 'scope_type' => 'NODE', 'scope_id' => $this->deep, 'includes_descendants' => false]]])->assertForbidden();
    }

    public function test_create_user_and_driver_is_atomic_idempotent_and_link_conflicts_preserve_account(): void
    {
        $driverRole = (string) RecordFixtureQuery::table('roles')->where('role_code', 'driver')->value('role_id');
        $input = $this->userInput('new-driver', $driverRole, $this->first) + ['operational_profile' => [
            'kind' => 'DRIVER',
            'mode' => 'CREATE',
            'driver' => [
                'driver_code' => 'D-NEW',
                'display_name' => 'New Driver',
                'home_node_id' => $this->first,
                'capabilities' => ['PICKUP', 'DELIVERY'],
            ],
        ]];
        $key = (string) random_int(1, 2000000000);
        $id = $this->withToken($this->token)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/iam/users', $input)->assertCreated()->json('data.user_id');
        $this->withToken($this->token)->postJson('/api/v1/iam/users', $input)->assertCreated()->assertJsonPath('data.user_id', $id);
        $driver = RecordFixtureQuery::table('drivers')->where('user_id', $id)->first();
        $this->assertNotNull($driver);
        $this->withToken($this->token)->getJson('/api/v1/iam/users/'.$id)->assertOk()->assertJsonPath('data.driver_profile.driver_id', $driver->driver_id);
        $bad = [...$input, 'username' => 'rollback-driver'];
        $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/users', $bad)->assertConflict();
        $this->assertDatabaseMissingPublic('users', ['username' => 'rollback-driver']);
        $unlinked = $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/fleet/drivers', [
            'driver_code' => 'UNLINKED',
            'display_name' => 'Existing Driver',
            'home_node_id' => $this->first,
            'capabilities' => ['PICKUP'],
        ])->assertCreated()->json('data');
        $link = $this->userInput('linked-driver', $driverRole, $this->first) + ['operational_profile' => [
            'kind' => 'DRIVER',
            'mode' => 'LINK',
            'existing_id' => $unlinked['driver_id'],
            'expected_version' => $unlinked['version'],
        ]];
        $linkedId = $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/users', $link)->assertCreated()->json('data.user_id');
        $this->assertDatabaseHasPublic('drivers', ['driver_id' => $unlinked['driver_id'], 'user_id' => $linkedId]);
        $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/users', [...$link, 'username' => 'stolen-driver'])->assertConflict();
        $this->assertDatabaseMissingPublic('users', ['username' => 'stolen-driver']);
    }

    public function test_node_onboarding_many_operators_and_assignment_edit_keep_history(): void
    {
        $role = $this->role('operator', ['consignment.view']);
        $input = [
            ...$this->userInput('node-founder', $role, $this->first),
            'assignments' => [],
            'operational_profile' => [
                'kind' => 'NODE',
                'mode' => 'CREATE',
                'role_id' => $role,
                'node' => [
                    'node_code' => 'NEW-HUB',
                    'node_title' => 'Head Office Hub',
                    'node_type' => 'HUB',
                    'area_id' => $this->root,
                    'capabilities' => ['CONSOLIDATION'],
                    'address' => ['country_code' => 'IR'],
                ],
            ],
        ];
        $founder = $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/users', $input)->assertCreated()->json('data.user_id');
        $hub = (string) RecordFixtureQuery::table('nodes')->where('node_code', 'NEW-HUB')->value('node_id');
        $second = $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/users', [
            ...$this->userInput('second-operator', $role, $hub),
            'assignments' => [],
            'operational_profile' => ['kind' => 'NODE', 'mode' => 'LINK', 'role_id' => $role, 'existing_id' => $hub],
        ])->assertCreated()->json('data.user_id');
        $ids = $this->withToken($this->token)->getJson('/api/v1/iam/users?node_id='.$hub)->assertOk()->json('data');
        $this->assertContains($founder, array_column($ids, 'user_id'));
        $this->assertContains($second, array_column($ids, 'user_id'));
        $old = RecordFixtureQuery::table('user_role_assignments')->where('user_id', $second)->value('assignment_id');
        $replacement = $this->withToken($this->token)->patchJson("/api/v1/iam/users/{$second}/role-assignments/{$old}", ['role_id' => $role, 'scope_type' => 'AREA', 'scope_id' => $this->root, 'includes_descendants' => true])->assertOk()->json('data.assignment_id');
        $this->assertNotSame($old, $replacement);
        $this->assertDatabaseHasPublic('user_role_assignments', ['assignment_id' => $old, 'status' => 'REVOKED']);
        $this->withToken($this->token)->getJson('/api/v1/iam/users/'.$second)->assertOk()->assertJsonFragment(['scope_title' => 'ROOT']);
    }

    public function test_limited_manager_cannot_take_over_admin_or_attach_foreign_profiles(): void
    {
        $manager = $this->user($this->hq, 'local-manager')['user_id'];
        $role = $this->role('local-manager', ['iam.users.view', 'iam.users.manage', 'iam.roles.assign', 'consignment.view']);
        $this->assign($manager, $role, 'NODE', $this->first);
        $token = $this->login('local-manager')['token'];
        $this->withToken($token)->postJson("/api/v1/iam/users/{$this->admin}/temporary-password", ['temporary_password' => 'CannotTakeOver123!'])->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/iam/users?node_id='.$this->deep)->assertForbidden();
        $foreignHq = $this->tenant('FOREIGN')['hq_id'];
        $foreignUser = $this->user($foreignHq, 'foreign-user')['user_id'];
        $this->withToken($this->token)->getJson('/api/v1/iam/users/'.$foreignUser)->assertForbidden();
        $input = $this->userInput('foreign-driver-link', $this->role, $this->first) + ['operational_profile' => ['kind' => 'DRIVER', 'mode' => 'LINK', 'existing_id' => (string) random_int(1, 2000000000), 'expected_version' => 1]];
        $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/iam/users', $input)->assertForbidden();
        $this->assertDatabaseMissingPublic('users', ['username' => 'foreign-driver-link']);
    }

    public function test_existing_user_link_checks_version_and_rejects_unknown_profile_fields(): void
    {
        $target = $this->user($this->hq, 'existing-account')['user_id'];
        $this->assign($target, $this->role, 'NODE', $this->first);
        $driver = $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/fleet/drivers', [
            'driver_code' => 'EXISTING',
            'display_name' => 'Existing Driver',
            'home_node_id' => $this->first,
            'capabilities' => ['PICKUP'],
        ])->assertCreated()->json('data');
        $url = "/api/v1/iam/users/{$target}/operational-profile";
        $profile = [
            'kind' => 'DRIVER',
            'mode' => 'LINK',
            'existing_id' => $driver['driver_id'],
            'expected_version' => $driver['version'],
        ];
        $this->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson($url, ['operational_profile' => [...$profile, 'unexpected' => true]])->assertUnprocessable();
        $this->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson($url, ['operational_profile' => [...$profile, 'expected_version' => 999]])->assertConflict();
        $this->assertDatabaseHasPublic('drivers', ['driver_id' => $driver['driver_id'], 'user_id' => null]);
        $this->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson($url, ['operational_profile' => $profile])->assertOk()->assertJsonPath('data.driver_profile.driver_id', $driver['driver_id']);
        $this->assertDatabaseHasPublic('drivers', ['driver_id' => $driver['driver_id'], 'user_id' => $target]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $this->hq = $this->tenant('ONBOARD')['hq_id'];
        $this->admin = $this->user($this->hq, 'onboard-admin')['user_id'];
        foreach (RecordFixtureQuery::table('permissions')->distinct()->pluck('module_code') as $module) {
            RecordFixtureQuery::table('tenant_module_entitlements')->insert([
                'entitlement_id' => (string) random_int(1, 2000000000),
                'hq_id' => $this->hq,
                'module_code' => $module,
                'status' => 'ENABLED',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->role = $this->role('all', RecordFixtureQuery::table('permissions')->pluck('permission_code')->all());
        $this->assign($this->admin, $this->role, 'TENANT');
        $this->token = $this->login('onboard-admin')['token'];
        $this->root = $this->area('ROOT');
        $this->child = $this->area('CHILD', $this->root);
        $this->leaf = $this->area('LEAF', $this->child);
        $this->first = $this->node('ONE', $this->root);
        $this->second = $this->node('TWO', $this->root);
        $this->deep = $this->node('DEEP', $this->leaf);
    }

    private function role(string $code, array $permissions): string
    {
        $id = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('roles')->insert([
            'role_id' => $id,
            'hq_id' => $this->hq,
            'owner_key' => $this->hq,
            'role_code' => $code,
            'role_title' => $code,
            'role_kind' => 'CUSTOM',
            'is_cloneable' => true,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (RecordFixtureQuery::table('permissions')->whereIn('permission_code', $permissions)->pluck('permission_id') as $permission) {
            RecordFixtureQuery::table('role_permissions')->insert([
                'role_permission_id' => (string) random_int(1, 2000000000),
                'role_id' => $id,
                'permission_id' => $permission,
                'created_at' => now(),
            ]);
        }

        return $id;
    }

    private function assign(string $user, string $role, string $type, ?string $id = null, bool $descendants = false): void
    {
        RecordFixtureQuery::table('user_role_assignments')->insert([
            'assignment_id' => (string) random_int(1, 2000000000),
            'hq_id' => $this->hq,
            'user_id' => $user,
            'role_id' => $role,
            'scope_type' => $type,
            'scope_id' => $id,
            'includes_descendants' => $descendants,
            'status' => 'ACTIVE',
            'active_slot' => hash('sha256', "{$user}|{$role}|{$type}|".($id ?? '-')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app->make(AuthorizationCacheInvalidator::class)->invalidateUser($user);
    }

    private function area(string $code, ?string $parent = null): string
    {
        return $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/network/areas', ['area_code' => $code, 'area_title' => $code, 'parent_area_id' => $parent])->assertCreated()->json('data.area_id');
    }

    private function node(string $code, string $area): string
    {
        return $this->withToken($this->token)->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/network/nodes', [
            'node_code' => $code,
            'node_title' => $code,
            'node_type' => 'HUB',
            'area_id' => $area,
            'capabilities' => ['CONSOLIDATION'],
            'address' => ['country_code' => 'IR'],
        ])->assertCreated()->json('data.node_id');
    }

    private function userInput(string $username, string $role, string $node): array
    {
        return [
            'creation_mode' => 'DIRECT_ACTIVE',
            'username' => $username,
            'first_name' => 'New',
            'last_name' => 'User',
            'temporary_password' => 'Strong!Pass123',
            'assignments' => [['role_id' => $role, 'scope_type' => 'NODE', 'scope_id' => $node, 'includes_descendants' => false]],
        ];
    }
}
