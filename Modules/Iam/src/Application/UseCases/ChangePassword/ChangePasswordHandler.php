<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ChangePassword;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\CredentialWriterInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Repositories\CredentialRepositoryInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Domain\Policies\PasswordPolicy;

final readonly class ChangePasswordHandler
{
    public function __construct(
        private PasswordPolicy $passwordPolicy,
        private ConnectionInterface $connection,
        private CredentialWriterInterface $credentialWriter,
        private ClockInterface $clock,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private CredentialRepositoryInterface $credentialRepository,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(ChangePasswordCommand $command): ChangePasswordResult
    {
        $actor = $command->actor;
        $currentPassword = $command->currentPassword;
        $newPassword = $command->newPassword;
        $correlationId = $command->correlationId;
        $this->passwordPolicy->assertValid($newPassword);
        $credential = $this->credentialRepository->findForUser($actor->userId);
        if ($credential === null || ! password_verify($currentPassword, (string) $credential->password_hash)) {
            throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'iam.invalid_credentials');
        }
        $this->connection->transaction(function () use ($actor, $newPassword, $correlationId): void {
            $this->credentialWriter->upsertPassword($actor->userId, $newPassword);
            $this->userRepository->update($actor->userId, ['must_change_password' => false]);
            $this->sessionLifecycle->revokeOtherSessions($actor->userId, $actor->sessionId, 'PASSWORD_CHANGED');
            $this->auditWriter->write($actor->hqId, $actor->userId, 'PASSWORD_CHANGED', 'USER', $actor->userId, $correlationId);
        }, attempts: 3);

        return new ChangePasswordResult;
    }
}
