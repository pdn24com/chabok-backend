<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ChangePassword;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Repositories\CredentialRepository;
use Modules\Identity\Application\Repositories\SessionRepository;
use Modules\Identity\Application\Services\CredentialWriter;
use Modules\Identity\Application\Services\SessionLifecycle;
use Modules\Identity\Domain\PasswordPolicy;
use Modules\User\Application\Repositories\UserRepository;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private CredentialRepository $credentials,
        private TransactionManager $transactions,
        private CredentialWriter $credentialWriter,
        private UserRepository $users,
        private Clock $clock,
        private SessionRepository $sessionRepository,
        private SessionLifecycle $sessionLifecycle,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(ChangePasswordCommand $command): ChangePasswordResult
    {
        $this->execute($command->actor, $command->currentPassword, $command->newPassword, $command->correlationId);
        return new ChangePasswordResult();
    }

    private function execute(AuthenticatedPrincipal $actor, string $currentPassword, string $newPassword, string $correlationId): void
    {
        $this->passwordPolicy->assertValid($newPassword);
        $credential = $this->credentials->findForUser($actor->userId);
        if ($credential === null || !password_verify($currentPassword, (string) $credential->password_hash)) {
            throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'Invalid credentials.');
        }
        $this->transactions->run(function () use ($actor, $newPassword, $correlationId): void {
            $this->credentialWriter->upsertPassword($actor->userId, $newPassword);
            $this->users->update($actor->userId, ['must_change_password' => false, 'updated_at' => $this->clock->now()]);
            foreach ($this->sessionRepository->activeForUser($actor->userId, $actor->sessionId) as $session) {
                $this->sessionLifecycle->revokeSession((string) $session->session_id, 'PASSWORD_CHANGED');
            }
            $this->audit->write($actor->hqId, $actor->userId, 'PASSWORD_CHANGED', 'USER', $actor->userId, $correlationId);
        });
    }
}
