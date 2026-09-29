<?php

declare(strict_types=1);

namespace Modules\Iam\Tests\Unit;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Services\UserAccessGuard;
use Modules\Iam\Application\UseCases\TransitionUser\TransitionUserCommand;
use Modules\Iam\Application\UseCases\TransitionUser\TransitionUserHandler;
use Modules\Iam\Domain\Enums\UserStatus;
use Modules\Iam\Domain\Exceptions\UserLifecycleViolation;
use Modules\Iam\Domain\Policies\UserLifecyclePolicy;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Modules\Iam\Infrastructure\Repositories\EloquentUserRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use RuntimeException;
use Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TransitionUserHandlerTest extends TestCase
{
    public function test_suspension_preserves_transaction_audit_event_and_session_revocation(): void
    {
        $dependencies = $this->dependencies();
        $command = $this->command();
        $dependencies['userAdministrationAuthorizer']->expects(self::once())->method('assertCan')->with($command->actor, 'iam.users.manage', '234618071');
        $dependencies['userScopeAuthorizer']->expects(self::once())->method('assertTarget')->with($command->actor, '55182337', 'iam.users.manage');
        $dependencies['sessionLifecycle']->expects(self::once())->method('revokeUserSessions')->with('55182337', 'USER_SUSPENDED')->willReturnCallback(function (): int {
            self::assertSame(1, DB::connection()->transactionLevel());
            self::assertSame('SUSPENDED', UserRecord::query()->value('status'));

            return 2;
        });
        $dependencies['auditWriter']->expects(self::once())->method('write')->willReturnCallback(function ($hq, $actor, $action, $type, $id, $correlation, $before, $after): void {
            self::assertSame(1, DB::connection()->transactionLevel());
            self::assertSame(['status' => 'ACTIVE'], $before);
            self::assertSame(['status' => 'SUSPENDED'], $after);
        });
        $dependencies['outboxWriter']->expects(self::once())->method('write')->willReturnCallback(function ($hq, $type, $id, $event, $correlation, $payload): void {
            self::assertSame(1, DB::connection()->transactionLevel());
            self::assertSame(['user_id' => '55182337', 'from' => 'ACTIVE', 'to' => 'SUSPENDED'], $payload);
        });
        $result = $this->handler($dependencies)->handle($command);
        self::assertSame('55182337', $result->user_id);
        self::assertSame('SUSPENDED', $result->status);
        $user = UserRecord::query()->firstOrFail();
        self::assertSame('SUSPENDED', $user->status);
        self::assertSame('2026-09-01 00:00:00', $user->activated_at);
        self::assertSame('2026-09-16 12:00:00', $user->getRawOriginal('updated_at'));
        self::assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_scope_denial_never_reads_or_mutates_a_user(): void
    {
        $dependencies = $this->dependencies();
        $dependencies['userScopeAuthorizer']->method('assertTarget')->willThrowException(new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied'));
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $this->handler($dependencies)->handle($this->command());
            self::fail('Scope denial must stop the operation.');
        } catch (ApiException $error) {
            self::assertSame(ApiErrorCode::ScopeAccessDenied, $error->errorCode);
            self::assertSame([], DB::connection()->getQueryLog());
            self::assertSame(0, DB::connection()->transactionLevel());
        } finally {
            DB::connection()->disableQueryLog();
        }
    }

    public function test_cross_tenant_user_is_rejected_before_mutation(): void
    {
        UserRecord::query()->update(['hq_id' => '136369301']);
        $dependencies = $this->dependencies();
        $dependencies['sessionLifecycle']->expects(self::never())->method('revokeUserSessions');
        $dependencies['auditWriter']->expects(self::never())->method('write');
        $dependencies['outboxWriter']->expects(self::never())->method('write');
        try {
            $this->handler($dependencies)->handle($this->command());
            self::fail('Cross-tenant user was accepted.');
        } catch (ApiException $error) {
            self::assertSame(ApiErrorCode::ResourceNotFound, $error->errorCode);
            self::assertSame(404, $error->httpStatus);
            self::assertSame('ACTIVE', UserRecord::query()->value('status'));
        }
    }

    public function test_invited_user_cannot_bypass_activation_proof_through_admin_transition(): void
    {
        UserRecord::query()->update(['status' => 'INVITED']);
        $dependencies = $this->dependencies();
        $dependencies['outboxWriter']->expects(self::never())->method('write');
        try {
            $this->handler($dependencies)->handle($this->command('ACTIVE'));
            self::fail('Activation proof must not be bypassed.');
        } catch (UserLifecycleViolation) {
            self::assertSame('INVITED', UserRecord::query()->value('status'));
        }
    }

    public function test_audit_failure_rolls_back_the_native_user_update(): void
    {
        $dependencies = $this->dependencies();
        $dependencies['auditWriter']->method('write')->willThrowException(new RuntimeException('Audit failure'));
        $dependencies['outboxWriter']->expects(self::never())->method('write');
        try {
            $this->handler($dependencies)->handle($this->command());
            self::fail('Audit failure must propagate.');
        } catch (RuntimeException $error) {
            self::assertSame('Audit failure', $error->getMessage());
            self::assertSame('ACTIVE', UserRecord::query()->value('status'));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.user_transition' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('user_transition');
        DB::connection()->getPdo()->sqliteCreateCollation('utf8mb4_bin', strcmp(...));
        (require glob(base_path('Modules/Iam/database/migrations/*_create_users.php'))[0])->up();
        UserRecord::query()->forceCreate(['user_id' => '55182337', 'hq_id' => '234618071', 'first_name' => 'Target', 'last_name' => 'User',
            'display_name' => 'Target User', 'status' => 'ACTIVE', 'activated_at' => '2026-09-01 00:00:00']);
    }

    private function dependencies(): array
    {
        $dependencies = ['connection' => DB::connection(), 'userRepository' => new EloquentUserRepository];
        foreach ([
            'userAdministrationAuthorizer' => UserAdministrationAuthorizerInterface::class,
            'userScopeAuthorizer' => UserScopeAuthorizerInterface::class,
            'clock' => ClockInterface::class,
            'sessionLifecycle' => SessionLifecycleInterface::class,
            'auditWriter' => AuditWriterInterface::class,
            'outboxWriter' => OutboxWriterInterface::class,
        ] as $name => $type) {
            $dependencies[$name] = $this->createMock($type);
        }
        $dependencies['clock']->method('now')->willReturn(new DateTimeImmutable('2026-09-16T12:00:00Z'));

        return $dependencies;
    }

    private function handler(array $dependencies): TransitionUserHandler
    {
        return new TransitionUserHandler(...['userAccessGuard' => new UserAccessGuard, 'userLifecyclePolicy' => new UserLifecyclePolicy, ...$dependencies]);
    }

    private function command(string $status = 'SUSPENDED'): TransitionUserCommand
    {
        return new TransitionUserCommand(new AuthenticatedPrincipal('147232623', 'session', '234618071', false), '55182337', UserStatus::from($status), '91733773');
    }
}
