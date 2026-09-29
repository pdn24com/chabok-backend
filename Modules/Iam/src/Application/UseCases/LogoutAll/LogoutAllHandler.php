<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\LogoutAll;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\VerificationProofConsumerInterface;
use Modules\Iam\Application\Repositories\CredentialRepositoryInterface;
use Modules\Iam\Domain\Enums\OtpPurpose;

final readonly class LogoutAllHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private VerificationProofConsumerInterface $verificationProofConsumer,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private CredentialRepositoryInterface $credentialRepository,
    ) {}

    public function handle(LogoutAllCommand $command): LogoutAllResult
    {
        $actor = $command->actor;
        $currentPassword = $command->currentPassword;
        $verificationToken = $command->verificationToken;
        $correlationId = $command->correlationId;
        if ($currentPassword === null && $verificationToken === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', ['current_password' => ['iam.exactly_one_step_up_proof_is_required']]);
        }

        return new LogoutAllResult($this->connection->transaction(function () use ($actor, $currentPassword, $verificationToken, $correlationId): int {
            if ($currentPassword !== null) {
                $credential = $this->credentialRepository->lockForUser($actor->userId);
                if ($credential === null || ! password_verify($currentPassword, (string) $credential->password_hash)) {
                    throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'iam.invalid_credentials');
                }
            } else {
                $this->verificationProofConsumer->consumeVerificationToken((string) $verificationToken, OtpPurpose::PASSWORD_RESET, $actor->userId);
            }
            $count = $this->sessionLifecycle->revokeUserSessions($actor->userId, 'LOGOUT_ALL');
            $this->auditWriter->write($actor->hqId, $actor->userId, 'AUTH_LOGOUT_ALL', 'USER', $actor->userId, $correlationId);

            return $count;
        }, attempts: 3));
    }
}
