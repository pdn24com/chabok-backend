<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ResetPassword;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Iam\Application\Contracts\CredentialWriterInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\VerificationProofConsumerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Domain\Enums\OtpPurpose;
use Modules\Iam\Domain\Policies\PasswordPolicy;

final readonly class ResetPasswordHandler
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private ConnectionInterface $connection,
        private VerificationProofConsumerInterface $verificationProofConsumer,
        private CredentialWriterInterface $credentialWriter,
        private ClockInterface $clock,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(ResetPasswordCommand $command): ResetPasswordResult
    {
        $verificationToken = $command->verificationToken;
        $newPassword = $command->newPassword;
        $correlationId = $command->correlationId;
        $this->passwordPolicy->assertValid($newPassword);

        return $this->connection->transaction(function () use ($verificationToken, $newPassword, $correlationId): ResetPasswordResult {
            $userId = $this->verificationProofConsumer->consumeVerificationToken($verificationToken, OtpPurpose::PASSWORD_RESET);
            $this->credentialWriter->upsertPassword($userId, $newPassword);
            $this->userRepository->update($userId, ['must_change_password' => false]);
            $revoked = $this->sessionLifecycle->revokeUserSessions($userId, 'PASSWORD_RESET');
            $user = $this->userRepository->find($userId);
            $this->auditWriter->write($user->hq_id ?? null, $userId, 'PASSWORD_RESET', 'USER', $userId, $correlationId);

            return new ResetPasswordResult($userId, $revoked);
        }, attempts: 3);
    }
}
