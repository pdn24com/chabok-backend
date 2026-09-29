<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\Crypt;
use LogicException;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Mappers\UserCreationInput;
use Modules\Iam\Application\Ports\InitialAssignmentWriterInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Services\UserIdentifierResolver;
use Modules\Iam\Application\UseCases\CreateUser\CreateUserCommand;
use Modules\Iam\Application\UseCases\CreateUser\CreateUserHandler;
use Modules\Iam\Application\UseCases\GetUser\GetUserCommand;
use Modules\Iam\Application\UseCases\GetUser\GetUserHandler;
use Modules\Iam\Application\UseCases\ListUsers\ListUsersCommand;
use Modules\Iam\Application\UseCases\ListUsers\ListUsersHandler;
use Modules\Iam\Application\UseCases\TransitionUser\TransitionUserCommand;
use Modules\Iam\Application\UseCases\TransitionUser\TransitionUserHandler;
use Modules\Iam\Domain\Enums\UserStatus;
use Tests\Support\AreaHierarchyFixtures;
use Tests\Support\RecordFixtureQuery;

final class PersistenceAndAdministrationTest extends MySqlRedisTestCase
{
    public function test_mysql_constraints_and_area_cycle_policy_are_enforced(): void
    {
        $tenant = $this->tenant();
        $ids = [(string) random_int(1, 2000000000), (string) random_int(1, 2000000000), (string) random_int(1, 2000000000)];
        foreach ($ids as $index => $id) {
            RecordFixtureQuery::table('areas')->insert([
                'area_id' => $id,
                'hq_id' => $tenant['hq_id'],
                'area_title' => "Area {$index}",
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $service = $this->app->make(AreaHierarchyFixtures::class);
        $service->addEdge($tenant['hq_id'], $ids[0], $ids[1]);
        $service->addEdge($tenant['hq_id'], $ids[1], $ids[2]);
        $this->assertEqualsCanonicalizing([$ids[1], $ids[2]], $service->descendantIds($tenant['hq_id'], $ids[0]));
        $this->expectException(ApiException::class);
        $service->addEdge($tenant['hq_id'], $ids[2], $ids[0]);
    }

    public function test_global_normalized_identifier_detection_crosses_identifier_types(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'global-id');
        $store = $this->app->make(UserIdentifierResolver::class);
        $this->assertTrue($store->identifiersExist(['global-id']));
        $this->assertFalse($store->identifiersExist(['unused-id']));
    }

    public function test_direct_active_and_invitation_rules_are_transactional_and_secret_safe(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant['hq_id'], 'admin-user');
        $this->allowAdministration();
        $service = $this->app->make(CreateUserHandler::class);
        $principal = new AuthenticatedPrincipal($actor['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false);
        $assignment = [[
            'role_id' => (string) random_int(1, 2000000000),
            'scope_type' => 'TENANT',
            'scope_id' => null,
            'includes_descendants' => false,
        ]];
        $direct = $service->handle(new CreateUserCommand($principal, UserCreationInput::user([
            'creation_mode' => 'DIRECT_ACTIVE',
            'username' => 'direct-user',
            'first_name' => 'Direct',
            'last_name' => 'User',
            'temporary_password' => 'Temporary!Pass123',
            'assignments' => $assignment,
        ]), '57013984'));
        $this->assertSame('ACTIVE', $direct['status']);
        $this->assertTrue((bool) $direct['must_change_password']);
        $this->assertArrayNotHasKey('temporary_password', $direct->getAttributes());
        $this->assertDatabaseHasPublic('authentication_credentials', ['user_id' => $direct['user_id']]);
        $invited = $service->handle(new CreateUserCommand($principal, UserCreationInput::user([
            'creation_mode' => 'EMAIL_INVITATION',
            'email' => 'invited@example.com',
            'first_name' => 'Invited',
            'last_name' => 'User',
            'assignments' => $assignment,
        ]), '227743837'));
        $this->assertSame('INVITED', $invited['status']);
        $this->assertDatabaseMissingPublic('authentication_credentials', ['user_id' => $invited['user_id']]);
        $this->assertDatabaseHasPublic('user_invitations', ['user_id' => $invited['user_id'], 'channel' => 'EMAIL']);
        $auditAndOutbox = json_encode([RecordFixtureQuery::table('audit_events')->get(), RecordFixtureQuery::table('outbox_events')->get()], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Temporary!Pass123', $auditAndOutbox);
        $delivery = json_decode((string) RecordFixtureQuery::table('outbox_events')->where('event_type', 'identity.invitation.delivery.requested')->where('hq_id', $tenant['hq_id'])->orderByDesc('created_at')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
        $invitationToken = Crypt::decryptString($delivery['delivery_ciphertext']);
        $this->postJson('/api/v1/auth/password/activate', ['invitation_token' => $invitationToken, 'new_password' => 'Activated!Pass456'])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->assertDatabaseHasPublic('users', ['user_id' => $invited['user_id'], 'status' => 'ACTIVE']);
        $this->assertDatabaseHasPublic('authentication_credentials', ['user_id' => $invited['user_id']]);
    }

    public function test_cross_tenant_user_guess_is_denied(): void
    {
        $tenantA = $this->tenant('HQ-A');
        $tenantB = $this->tenant('HQ-B');
        $actor = $this->user($tenantA['hq_id'], 'tenant-a-admin');
        $target = $this->user($tenantB['hq_id'], 'tenant-b-user');
        $this->allowAdministration();
        $principal = new AuthenticatedPrincipal($actor['user_id'], (string) random_int(1, 2000000000), $tenantA['hq_id'], false);
        try {
            $this->app->make(GetUserHandler::class)->handle(new GetUserCommand($principal, $target['user_id']));
            $this->fail('Cross-tenant user access should have been denied.');
        } catch (ApiException $exception) {
            $this->assertSame(403, $exception->httpStatus);
            $this->assertSame('TENANT_ACCESS_DENIED', $exception->errorCode->value);
        }
    }

    public function test_user_administration_requires_an_effective_permission(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant['hq_id'], 'closed-admin');
        $principal = new AuthenticatedPrincipal($actor['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false);
        $this->expectException(ApiException::class);
        $this->app->make(ListUsersHandler::class)->handle(new ListUsersCommand($principal, 1, 25, null, null));
    }

    public function test_user_create_idempotency_replays_and_rejects_changed_fingerprint(): void
    {
        $tenant = $this->tenant();
        $this->user($tenant['hq_id'], 'idempotent-admin');
        $login = $this->login('idempotent-admin');
        $this->allowAdministration();
        $key = 'idempotency-key-000001';
        $payload = [
            'creation_mode' => 'DIRECT_ACTIVE',
            'username' => 'idempotent-user',
            'first_name' => 'Idempotent',
            'last_name' => 'User',
            'temporary_password' => 'Temporary!Pass123',
            'assignments' => [[
                'role_id' => (string) random_int(1, 2000000000),
                'scope_type' => 'TENANT',
                'scope_id' => null,
                'includes_descendants' => false,
            ]],
        ];
        $correlationId = '1371394985';
        $first = $this->withToken($login['token'])->withHeader('X-Correlation-ID', $correlationId)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/iam/users', $payload);
        $second = $this->withToken($login['token'])->withHeader('X-Correlation-ID', $correlationId)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/iam/users', $payload);
        $first->assertCreated();
        $second->assertCreated()->assertExactJson($first->json());
        $this->assertSame(1, RecordFixtureQuery::table('users')->where('normalized_username', 'idempotent-user')->count());
        $payload['last_name'] = 'Changed';
        $this->withToken($login['token'])->withHeader('X-Correlation-ID', $correlationId)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/iam/users', $payload)->assertStatus(409)->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REUSED');
    }

    public function test_user_creation_rolls_back_identity_audit_and_outbox_when_assignment_boundary_fails(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant['hq_id'], 'rollback-admin');
        $this->app->instance(UserAdministrationAuthorizerInterface::class, new class implements UserAdministrationAuthorizerInterface
        {
            public function assertCan(AuthenticatedPrincipal $actor, string $permission, string $hqId): void {}
        });
        $this->app->instance(InitialAssignmentWriterInterface::class, new class implements InitialAssignmentWriterInterface
        {
            public function assign(string $hqId, string $userId, string $actorId, array $assignments, string $correlationId): void
            {
                throw new LogicException('Simulated assignment boundary failure.');
            }
        });
        $principal = new AuthenticatedPrincipal($actor['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false);
        try {
            $this->app->make(CreateUserHandler::class)->handle(new CreateUserCommand($principal, UserCreationInput::user([
                'creation_mode' => 'DIRECT_ACTIVE',
                'username' => 'rolled-back-user',
                'first_name' => 'Rolled',
                'last_name' => 'Back',
                'temporary_password' => 'Temporary!Pass123',
                'assignments' => [[
                    'role_id' => (string) random_int(1, 2000000000),
                    'scope_type' => 'TENANT',
                    'scope_id' => null,
                    'includes_descendants' => false,
                ]],
            ]), '842705270'));
            $this->fail('Unavailable assignment boundary must abort the whole create transaction.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('assignment boundary failure', $exception->getMessage());
        }
        $this->assertDatabaseMissingPublic('users', ['normalized_username' => 'rolled-back-user']);
        $this->assertSame(1, RecordFixtureQuery::table('authentication_credentials')->count());
        $this->assertDatabaseMissingPublic('audit_events', ['correlation_id' => '842705270']);
        $this->assertDatabaseMissingPublic('outbox_events', ['correlation_id' => '842705270']);
    }

    public function test_suspension_revokes_sessions_and_invalidates_existing_access_token(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user($tenant['hq_id'], 'suspend-admin');
        $target = $this->user($tenant['hq_id'], 'suspend-target');
        $targetLogin = $this->login('suspend-target');
        $this->allowAdministration();
        $principal = new AuthenticatedPrincipal($actor['user_id'], (string) random_int(1, 2000000000), $tenant['hq_id'], false);
        $result = $this->app->make(TransitionUserHandler::class)->handle(new TransitionUserCommand($principal, $target['user_id'], UserStatus::Suspended, '74381206'));
        $this->assertSame('SUSPENDED', $result['status']);
        $this->assertDatabaseHasPublic('user_sessions', ['user_id' => $target['user_id'], 'revoked_reason' => 'USER_SUSPENDED']);
        $this->withToken($targetLogin['token'])->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('error_code', 'AUTHENTICATION_REQUIRED');
    }

    private function allowAdministration(): void
    {
        // This boundary test deliberately substitutes authorization; scoped-access behavior has real integration coverage.
        $this->app->instance(UserScopeAuthorizerInterface::class, new class implements UserScopeAuthorizerInterface
        {
            public function visibleUserIds(AuthenticatedPrincipal $actor, ?string $nodeId = null): ?array
            {
                return null;
            }

            public function assertTarget(AuthenticatedPrincipal $actor, string $userId, string $permission): void {}
        });
        $this->app->instance(UserAdministrationAuthorizerInterface::class, new class implements UserAdministrationAuthorizerInterface
        {
            public function assertCan(AuthenticatedPrincipal $actor, string $permission, string $hqId): void {}
        });
        $this->app->instance(InitialAssignmentWriterInterface::class, new class implements InitialAssignmentWriterInterface
        {
            public function assign(string $hqId, string $userId, string $actorId, array $assignments, string $correlationId): void {}
        });
    }
}
