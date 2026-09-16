<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ResetPassword;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Identity\Application\Services\CredentialWriter;
use Modules\Identity\Application\Services\SessionLifecycle;
use Modules\Identity\Application\Services\VerificationProofConsumer;
use Modules\Identity\Domain\PasswordPolicy;
use Modules\User\Application\Repositories\UserRepository;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private TransactionManager $transactions,
        private VerificationProofConsumer $verificationProofConsumer,
        private CredentialWriter $credentialWriter,
        private UserRepository $users,
        private Clock $clock,
        private SessionLifecycle $sessionLifecycle,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(ResetPasswordCommand $command): ResetPasswordResult
    {
        return new ResetPasswordResult($this->execute($command->verificationToken, $command->newPassword, $command->correlationId));
    }

    private function execute(string $verificationToken, string $newPassword, string $correlationId): array
    {
        $this->passwordPolicy->assertValid($newPassword);
        return $this->transactions->run(function () use ($verificationToken, $newPassword, $correlationId): array {
            $userId = $this->verificationProofConsumer->consumeVerificationToken($verificationToken, 'PASSWORD_RESET');
            $this->credentialWriter->upsertPassword($userId, $newPassword);
            $this->users->update($userId, ['must_change_password' => false, 'updated_at' => $this->clock->now()]);
            $revoked = $this->sessionLifecycle->revokeUserSessions($userId, 'PASSWORD_RESET');
            $user = $this->users->findById($userId);
            $this->audit->write($user['hq_id'] ?? null, $userId, 'PASSWORD_RESET', 'USER', $userId, $correlationId);
            return ['user_id' => $userId, 'revoked_session_count' => $revoked];
        });
    }
}
