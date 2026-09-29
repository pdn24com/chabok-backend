<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\Cache;
use Modules\Authorization\Application\Catalogs\AuthorizationCatalog;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationInitialAssignmentWriter;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationNodeAccessValidator;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationPlatformContextValidator;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserAdministrationAuthorizer;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserAssignmentReader;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Foundation\Application\Ports\NodeAccessValidatorInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Ports\InitialAssignmentWriterInterface;
use Modules\Iam\Application\Ports\PlatformContextValidatorInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserAssignmentReaderInterface;
use Tests\Support\AuthorizationFixtures;
use Tests\Support\RecordFixtureQuery;

final class AuthorizationIntegrationTest extends MySqlRedisTestCase
{
    public function test_approved_catalog_seed_is_exact_and_idempotent(): void
    {
        $this->seedCatalog();

        $this->assertSame(count(AuthorizationCatalog::permissions()), RecordFixtureQuery::table('permissions')->count());
        $this->assertSame(count(AuthorizationCatalog::roles()), RecordFixtureQuery::table('roles')->count());
        $this->assertEqualsCanonicalizing(
            array_keys(AuthorizationCatalog::permissions()),
            RecordFixtureQuery::table('permissions')->pluck('permission_code')->all(),
        );
        foreach ([
            'iam.entitlements.manage', 'driver_app.access', 'driver.task.view',
            'driver.task.execute', 'exception.nok.submit', 'exception.npu.submit',
        ] as $deferred) {
            $this->assertDatabaseMissingPublic('permissions', ['permission_code' => $deferred]);
        }
        $this->assertDatabaseHasPublic('roles', [
            'role_code' => 'branch_manager', 'role_kind' => 'SYSTEM', 'is_cloneable' => false,
        ]);
        $this->assertDatabaseHasPublic('roles', [
            'role_code' => 'manifest_approver', 'role_kind' => 'TEMPLATE', 'is_cloneable' => true,
        ]);
    }

    public function test_production_boundaries_use_the_authorization_module(): void
    {
        $this->assertInstanceOf(
            AuthorizationUserAdministrationAuthorizer::class,
            $this->app->make(UserAdministrationAuthorizerInterface::class),
        );
        $this->assertInstanceOf(
            AuthorizationInitialAssignmentWriter::class,
            $this->app->make(InitialAssignmentWriterInterface::class),
        );
        $this->assertInstanceOf(
            AuthorizationUserAssignmentReader::class,
            $this->app->make(UserAssignmentReaderInterface::class),
        );
        $this->assertInstanceOf(
            AuthorizationNodeAccessValidator::class,
            $this->app->make(NodeAccessValidatorInterface::class),
        );
        $this->assertInstanceOf(
            AuthorizationPlatformContextValidator::class,
            $this->app->make(PlatformContextValidatorInterface::class),
        );
    }

    public function test_context_resolves_descendant_areas_nodes_and_redis_cache_invalidation(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant['hq_id'], 'context-manager');
        $this->entitlement($tenant['hq_id'], 'Foundation');
        $this->entitlement($tenant['hq_id'], 'IAM');
        [$parent, $child, $foreign] = $this->areaTree($tenant['hq_id']);
        $parentNode = $this->node($tenant['hq_id'], $parent, 'PARENT');
        $childNode = $this->node($tenant['hq_id'], $child, 'CHILD');
        $foreignNode = $this->node($tenant['hq_id'], $foreign, 'FOREIGN');
        $this->assignment($tenant['hq_id'], $actor['user_id'], 'branch_manager', 'AREA', $parent, true);
        $principal = $this->principal($actor);
        $service = $this->app->make(AuthorizationFixtures::class);

        $context = $service->resolve($principal);
        $this->assertSame([
            'hq_id' => $tenant['hq_id'],
            'code' => $tenant['hq_code'],
            'title' => $tenant['hq_title'],
        ], ['hq_id' => $context->tenant->hqId, 'code' => $context->tenant->code, 'title' => $context->tenant->title]);
        $this->assertContains('branch_manager', $context->roleCodes);
        $this->assertContains('node_context.view', $context->permissions);
        $this->assertEqualsCanonicalizing(
            [$parentNode, $childNode],
            $context->accessibleNodeIds,
        );
        $this->assertNotContains($foreignNode, $context->accessibleNodeIds);
        $this->assertNotNull(Cache::store('redis')->get("chabok:authz:effective:{$actor['user_id']}"));

        $service->invalidateUser($actor['user_id']);
        $this->assertNull(Cache::store('redis')->get("chabok:authz:effective:{$actor['user_id']}"));
    }

    public function test_gate_order_distinguishes_entitlement_permission_tenant_and_scope_denials(): void
    {
        $tenantA = $this->tenant('AUTH-A');
        $tenantB = $this->tenant('AUTH-B');
        $actor = $this->user($tenantA['hq_id'], 'gate-actor');
        $principal = $this->principal($actor);
        $service = $this->app->make(AuthorizationFixtures::class);

        $this->assertApiCode(
            fn () => $service->assertPermission($principal, 'iam.users.view', $tenantA['hq_id']),
            ApiErrorCode::EntitlementDisabled,
        );
        $this->entitlement($tenantA['hq_id'], 'IAM');
        $this->assertApiCode(
            fn () => $service->assertPermission($principal, 'iam.users.view', $tenantA['hq_id']),
            ApiErrorCode::PermissionDenied,
        );
        $this->assertApiCode(
            fn () => $service->assertPermission($principal, 'iam.users.view', $tenantB['hq_id']),
            ApiErrorCode::TenantAccessDenied,
        );

        $this->assignment($tenantA['hq_id'], $actor['user_id'], 'branch_manager', 'TENANT');
        $this->entitlement($tenantA['hq_id'], 'Foundation');
        $service->invalidateUser($actor['user_id']);
        $service->assertPermission($principal, 'iam.users.view', $tenantA['hq_id']);
        $this->assertApiCode(
            fn () => $service->assertNodeAccessible($principal, (string) random_int(1, 2000000000)),
            ApiErrorCode::ScopeAccessDenied,
        );
    }

    public function test_x_node_cannot_cross_tenant_or_exceed_descendant_scope(): void
    {
        $tenantA = $this->tenant('NODE-A');
        $tenantB = $this->tenant('NODE-B');
        $actor = $this->user($tenantA['hq_id'], 'node-manager');
        $this->entitlement($tenantA['hq_id'], 'Foundation');
        [$areaA, $areaB] = $this->areas($tenantA['hq_id'], 2);
        [$otherArea] = $this->areas($tenantB['hq_id'], 1);
        $allowed = $this->node($tenantA['hq_id'], $areaA, 'ALLOWED');
        $sameTenantDenied = $this->node($tenantA['hq_id'], $areaB, 'DENIED');
        $crossTenant = $this->node($tenantB['hq_id'], $otherArea, 'CROSS');
        $this->assignment($tenantA['hq_id'], $actor['user_id'], 'branch_manager', 'NODE', $allowed);
        $login = $this->login('node-manager');

        $this->withToken($login['token'])->withHeader('X-Node-Id', $allowed)
            ->getJson('/api/v1/me')->assertOk();
        $this->withToken($login['token'])->withHeader('X-Node-Id', $sameTenantDenied)
            ->getJson('/api/v1/me')->assertStatus(403)
            ->assertJsonPath('error_code', 'SCOPE_ACCESS_DENIED');
        $this->withToken($login['token'])->withHeader('X-Node-Id', $crossTenant)
            ->getJson('/api/v1/me')->assertStatus(403)
            ->assertJsonPath('error_code', 'TENANT_ACCESS_DENIED');
    }

    public function test_assignments_enforce_same_tenant_scope_delegation_duplicates_and_history(): void
    {
        $tenant = $this->tenant();
        $other = $this->tenant('OTHER');
        $actor = $this->user($tenant['hq_id'], 'assignment-manager');
        $target = $this->user($tenant['hq_id'], 'assignment-target');
        $foreign = $this->user($other['hq_id'], 'foreign-target');
        $this->enableBranchModules($tenant['hq_id']);
        $this->assignment($tenant['hq_id'], $actor['user_id'], 'branch_manager', 'TENANT');
        $service = $this->app->make(AuthorizationFixtures::class);
        $principal = $this->principal($actor);
        $input = [[
            'role_id' => $this->roleId('branch_operator'),
            'scope_type' => 'TENANT',
            'scope_id' => null,
            'includes_descendants' => false,
        ]];

        $created = $service->createAssignments(
            $principal,
            $target['user_id'],
            $input,
            '808851386',
        );
        $this->assertCount(1, $created);
        $this->assertApiCode(
            fn () => $service->createAssignments(
                $principal,
                $target['user_id'],
                $input,
                '16213424',
            ),
            ApiErrorCode::Conflict,
            409,
        );
        $this->assertApiCode(
            fn () => $service->createAssignments(
                $principal,
                $foreign['user_id'],
                $input,
                '1356360467',
            ),
            ApiErrorCode::TenantAccessDenied,
        );

        $service->revokeAssignment(
            $principal,
            $target['user_id'],
            $created[0]['assignment_id'],
            '196468244',
        );
        $this->assertDatabaseHasPublic('user_role_assignments', [
            'assignment_id' => $created[0]['assignment_id'],
            'status' => 'REVOKED',
            'active_slot' => null,
        ]);
        $replacement = $service->createAssignments(
            $principal,
            $target['user_id'],
            $input,
            '1825157418',
        );
        $this->assertNotSame($created[0]['assignment_id'], $replacement[0]['assignment_id']);
    }

    public function test_system_roles_are_immutable_and_custom_role_changes_invalidate_access(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant['hq_id'], 'role-admin');
        foreach (['IAM', 'Manifest'] as $module) {
            $this->entitlement($tenant['hq_id'], $module);
        }
        $this->assignment($tenant['hq_id'], $actor['user_id'], 'branch_manager', 'TENANT');
        $this->assignment($tenant['hq_id'], $actor['user_id'], 'hq_admin', 'TENANT');
        $this->assignment($tenant['hq_id'], $actor['user_id'], 'manifest_approver', 'TENANT');
        $service = $this->app->make(AuthorizationFixtures::class);
        $principal = $this->principal($actor);

        $this->assertApiCode(
            fn () => $service->updateRole(
                $principal,
                $this->roleId('branch_manager'),
                ['role_title' => 'Changed'],
                '1477882675',
            ),
            ApiErrorCode::ValidationError,
            422,
        );

        $custom = $service->cloneRole($principal, $this->roleId('manifest_approver'), [
            'role_code' => 'tenant_manifest_approver',
            'role_title' => 'Tenant Manifest Approver',
        ], '1025467437');
        $this->assertSame('CUSTOM', $custom['role_kind']);
        $updated = $service->updateRole($principal, $custom['role_id'], [
            'role_title' => 'Local Approver',
        ], '129434130');
        $this->assertSame('Local Approver', $updated['role_title']);
        $replaced = $service->replaceRolePermissions(
            $principal,
            $custom['role_id'],
            ['manifest.approve'],
            '57013984',
        );
        $this->assertSame(['manifest.approve'], $replaced['permission_codes']);
        $this->assertDatabaseHasPublic('audit_events', ['action_key' => 'ROLE_PERMISSIONS_REPLACED']);
        $this->assertDatabaseHasPublic('outbox_events', ['event_type' => 'iam.role.permissions_replaced']);
    }

    public function test_platform_super_admin_requires_null_hq_and_active_platform_assignment(): void
    {
        $tenant = $this->tenant();
        $this->entitlement($tenant['hq_id'], 'IAM');
        $platform = $this->platformUser('platform-admin');
        $this->platformAssignment($platform['user_id']);

        $login = $this->login('platform-admin');
        $login['response']->assertJsonPath('data.context.is_platform_admin', true)
            ->assertJsonPath('data.context.hq_id', null);
        $this->withToken($login['token'])->getJson('/api/v1/me/context')
            ->assertOk()->assertJsonPath('data.is_platform_admin', true);
        $this->withToken($login['token'])->getJson('/api/v1/iam/module-entitlements')
            ->assertOk();
    }

    public function test_platform_can_manage_global_operational_status_catalog_via_api(): void
    {
        $platform = $this->platformUser('status-platform');
        $this->platformAssignment($platform['user_id']);
        $login = $this->login('status-platform');
        $input = ['scope' => 'GLOBAL', 'code' => 'GLOBAL_NEW', 'title_fa' => 'وضعیت سراسری', 'tone' => 'info', 'is_active' => true, 'is_terminal' => false, 'sort_order' => 200];
        $created = $this->withToken($login['token'])->withHeader('Idempotency-Key', (string) random_int(1, 2000000000))->postJson('/api/v1/operational-statuses', $input)->assertCreated()->assertJsonPath('data.hq_id', null)->assertJsonPath('data.can_manage', true);
        $id = $created->json('data.status_id');
        unset($input['scope'],$input['code']);
        $this->withToken($login['token'])->patchJson('/api/v1/operational-statuses/'.$id, [...$input, 'expected_version' => 1, 'title_fa' => 'عنوان جدید'])->assertOk()->assertJsonPath('data.version', 2);
        $this->withToken($login['token'])->patchJson('/api/v1/operational-statuses/'.$id, [...$input, 'expected_version' => 1])->assertConflict();
        $this->withToken($login['token'])->getJson('/api/v1/operational-statuses')->assertOk()->assertJsonFragment(['code' => 'GLOBAL_NEW']);
    }

    public function test_all_authorization_routes_return_contract_envelopes(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant['hq_id'], 'route-manager');
        $target = $this->user($tenant['hq_id'], 'route-target');
        $this->enableBranchModules($tenant['hq_id']);
        $this->assignment($tenant['hq_id'], $actor['user_id'], 'branch_manager', 'TENANT');
        $login = $this->login('route-manager');
        $token = $login['token'];
        $roleId = $this->roleId('branch_operator');

        foreach ([
            '/api/v1/me/context',
            '/api/v1/context/nodes',
            '/api/v1/iam/roles',
            "/api/v1/iam/roles/{$roleId}",
            '/api/v1/iam/permissions',
        ] as $uri) {
            $this->withToken($token)->getJson($uri)->assertOk()
                ->assertJsonStructure(['data', 'meta', 'correlation_id']);
        }
        $created = $this->withToken($token)->postJson(
            "/api/v1/iam/users/{$target['user_id']}/role-assignments",
            ['assignments' => [[
                'role_id' => $roleId,
                'scope_type' => 'TENANT',
                'scope_id' => null,
                'includes_descendants' => false,
            ]]],
        )->assertCreated()->json('data.0.assignment_id');
        $this->withToken($token)
            ->getJson("/api/v1/iam/users/{$target['user_id']}")
            ->assertOk()
            ->assertJsonPath('data.assignments.0.assignment_id', $created)
            ->assertJsonPath('data.assignments.0.role_id', $roleId)
            ->assertJsonPath('data.assignments.0.scope_type', 'TENANT');
        $this->withToken($token)->deleteJson(
            "/api/v1/iam/users/{$target['user_id']}/role-assignments/{$created}",
        )->assertOk()->assertJsonPath('data.success', true);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    private function seedCatalog(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
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

    /** @return list<string> */
    private function areas(string $hqId, int $count): array
    {
        $ids = [];
        for ($index = 0; $index < $count; $index++) {
            $ids[] = $id = (string) random_int(1, 2000000000);
            RecordFixtureQuery::table('areas')->insert([
                'area_id' => $id,
                'hq_id' => $hqId,
                'area_title' => "Area {$index}",
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    /** @return array{string, string, string} */
    private function areaTree(string $hqId): array
    {
        [$parent, $child, $foreign] = $this->areas($hqId, 3);
        RecordFixtureQuery::table('area_hierarchies')->insert([
            'area_hierarchy_id' => (string) random_int(1, 2000000000),
            'hq_id' => $hqId,
            'parent_area_id' => RecordFixtureQuery::table('areas')->where('area_id', $parent)->value('id'),
            'child_area_id' => RecordFixtureQuery::table('areas')->where('area_id', $child)->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$parent, $child, $foreign];
    }

    private function node(string $hqId, string $areaId, string $code): string
    {
        $id = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('nodes')->insert([
            'node_id' => $id,
            'hq_id' => $hqId,
            'area_id' => $areaId,
            'node_code' => $code,
            'node_title' => "Node {$code}",
            'node_type' => 'BRANCH',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @return array<string, mixed> */
    private function platformUser(string $identifier): array
    {
        $row = [
            'user_id' => (string) random_int(1, 2000000000),
            'hq_id' => null,
            'username' => $identifier,
            'normalized_username' => $identifier,
            'mobile' => null,
            'normalized_mobile' => null,
            'email' => null,
            'normalized_email' => null,
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'display_name' => 'Platform Admin',
            'status' => 'ACTIVE',
            'must_change_password' => false,
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        RecordFixtureQuery::table('users')->insert($row);
        RecordFixtureQuery::table('authentication_credentials')->insert([
            'credential_id' => (string) random_int(1, 2000000000),
            'user_id' => $row['user_id'],
            'password_hash' => password_hash('Strong!Pass123', PASSWORD_ARGON2ID),
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $row;
    }

    private function platformAssignment(string $userId): void
    {
        $roleId = $this->roleId('platform_super_admin');
        RecordFixtureQuery::table('user_role_assignments')->insert([
            'assignment_id' => (string) random_int(1, 2000000000),
            'hq_id' => null,
            'user_id' => $userId,
            'role_id' => $roleId,
            'scope_type' => 'PLATFORM',
            'scope_id' => null,
            'includes_descendants' => false,
            'status' => 'ACTIVE',
            'active_slot' => hash('sha256', "{$userId}|{$roleId}|PLATFORM|-"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $user */
    private function principal(array $user): AuthenticatedPrincipal
    {
        return new AuthenticatedPrincipal($user['user_id'], '', $user['hq_id'], false);
    }

    private function assertApiCode(
        callable $operation,
        ApiErrorCode $expected,
        int $status = 403,
    ): void {
        try {
            $operation();
            $this->fail("Expected {$expected->value}.");
        } catch (ApiException $exception) {
            $this->assertSame($expected, $exception->errorCode);
            $this->assertSame($status, $exception->httpStatus);
        }
    }
}
