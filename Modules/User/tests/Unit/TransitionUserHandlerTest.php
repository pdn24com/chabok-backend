<?php

declare(strict_types=1);

namespace Modules\User\Tests\Unit;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserScopeAuthorizer;
use Modules\User\Application\Contracts\UserSessionManager;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Application\UseCases\TransitionUser\TransitionUserCommand;
use Modules\User\Application\UseCases\TransitionUser\TransitionUserHandler;
use Modules\User\Domain\UserAdministrationPolicy;
use Modules\User\Domain\UserLifecyclePolicy;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
/** These tests boot neither Laravel nor a database: application dependencies are ports. */
#[AllowMockObjectsWithoutExpectations]

final class TransitionUserHandlerTest extends TestCase
{
    private function dependencies(): array
    {
        $dependencies = [];
        foreach ([
            'authorizer' => UserAdministrationAuthorizer::class,
            'scopeAuthorizer' => UserScopeAuthorizer::class,
            'transactions' => TransactionManager::class,
            'users' => UserRepository::class,
            'clock' => Clock::class,
            'sessions' => UserSessionManager::class,
            'audit' => AuditWriter::class,
            'outbox' => OutboxWriter::class,
        ] as $name => $type) {
            $dependencies[$name] = $this->createMock($type);
        }
        return $dependencies;
    }

    private function handler(array $dependencies): TransitionUserHandler
    {
        return new TransitionUserHandler(...['policy' => new UserAdministrationPolicy(), 'lifecycle' => new UserLifecyclePolicy(), ...$dependencies]);
    }

    public function test_suspension_preserves_transaction_audit_event_and_session_revocation(): void
    {
        $dependencies = $this->dependencies();
        $actor = new AuthenticatedPrincipal('admin', 'session', 'hq', false);
        $now = new \DateTimeImmutable('2026-09-16T12:00:00Z');
        $insideTransaction = false;
        $dependencies['authorizer']->expects(self::once())->method('assertCan')->with($actor, 'iam.users.manage', 'hq');
        $dependencies['scopeAuthorizer']->expects(self::once())->method('assertTarget')->with($actor, 'target', 'iam.users.manage');
        $dependencies['transactions']->expects(self::once())->method('run')->willReturnCallback(function (callable $operation) use (&$insideTransaction) {
            $insideTransaction = true;
            try {
                return $operation();
            } finally {
                $insideTransaction = false;
            }
        });
        $dependencies['users']->expects(self::once())->method('findTenantUserForUpdate')->with('hq', 'target')->willReturn(['user_id' => 'target', 'hq_id' => 'hq', 'status' => 'ACTIVE', 'activated_at' => '2026-09-01 00:00:00']);
        $dependencies['clock']->method('now')->willReturn($now);
        $dependencies['users']->expects(self::once())->method('update')->with('target', self::callback(function (array $changes) use (&$insideTransaction, $now): bool {
            return $insideTransaction && $changes === ['status' => 'SUSPENDED', 'activated_at' => '2026-09-01 00:00:00', 'updated_at' => $now];
        }));
        $dependencies['sessions']->expects(self::once())->method('revokeUserSessions')->with('target', 'USER_SUSPENDED')->willReturn(2);
        $dependencies['audit']->expects(self::once())->method('write')->willReturnCallback(function () use (&$insideTransaction): void {
            self::assertTrue($insideTransaction);
        });
        $dependencies['outbox']->expects(self::once())->method('write')->willReturnCallback(function () use (&$insideTransaction): void {
            self::assertTrue($insideTransaction);
        });
        $result = $this->handler($dependencies)->handle(new TransitionUserCommand($actor, 'target', 'SUSPENDED', 'correlation'));
        self::assertSame(['user_id' => 'target', 'status' => 'SUSPENDED'], $result->data);
        self::assertFalse($insideTransaction);
    }

    public function test_scope_denial_never_enters_a_transaction_or_reads_a_user(): void
    {
        $dependencies = $this->dependencies();
        $dependencies['scopeAuthorizer']->method('assertTarget')->willThrowException(new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.'));
        $dependencies['transactions']->expects(self::never())->method('run');
        $dependencies['users']->expects(self::never())->method('findTenantUserForUpdate');
        $this->expectException(ApiException::class);
        $this->handler($dependencies)->handle(new TransitionUserCommand(new AuthenticatedPrincipal('admin', 'session', 'hq', false), 'target', 'SUSPENDED', 'correlation'));
    }

    public function test_cross_tenant_repository_result_is_rejected_before_mutation(): void
    {
        $dependencies = $this->dependencies();
        $dependencies['transactions']->method('run')->willReturnCallback(fn(callable $operation) => $operation());
        $dependencies['users']->method('findTenantUserForUpdate')->willReturn(['hq_id' => 'another-hq', 'status' => 'ACTIVE']);
        $dependencies['users']->expects(self::never())->method('update');
        $dependencies['sessions']->expects(self::never())->method('revokeUserSessions');
        $dependencies['audit']->expects(self::never())->method('write');
        $dependencies['outbox']->expects(self::never())->method('write');
        try {
            $this->handler($dependencies)->handle(new TransitionUserCommand(new AuthenticatedPrincipal('admin', 'session', 'hq', false), 'target', 'SUSPENDED', 'correlation'));
            self::fail('Cross-tenant result was accepted.');
        } catch (ApiException $error) {
            self::assertSame(ApiErrorCode::TenantAccessDenied, $error->errorCode);
            self::assertSame(403, $error->httpStatus);
        }
    }

    public function test_invited_user_cannot_bypass_activation_proof_through_admin_transition(): void
    {
        $dependencies = $this->dependencies();
        $dependencies['transactions']->method('run')->willReturnCallback(fn(callable $operation) => $operation());
        $dependencies['users']->method('findTenantUserForUpdate')->willReturn(['hq_id' => 'hq', 'status' => 'INVITED']);
        $dependencies['users']->expects(self::never())->method('update');
        $dependencies['outbox']->expects(self::never())->method('write');
        $this->expectException(ApiException::class);
        $this->handler($dependencies)->handle(new TransitionUserCommand(new AuthenticatedPrincipal('admin', 'session', 'hq', false), 'target', 'ACTIVE', 'correlation'));
    }
}
