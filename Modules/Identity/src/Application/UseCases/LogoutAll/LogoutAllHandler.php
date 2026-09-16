<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\LogoutAll;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Repositories\CredentialRepository;
use Modules\Identity\Application\Services\SessionLifecycle;
use Modules\Identity\Application\Services\VerificationProofConsumer;

final readonly class LogoutAllHandler
{
    public function __construct(
        private TransactionManager $transactions,
        private CredentialRepository $credentials,
        private VerificationProofConsumer $verificationProofConsumer,
        private SessionLifecycle $sessionLifecycle,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(LogoutAllCommand $command): LogoutAllResult
    {
        return new LogoutAllResult($this->execute($command->actor, $command->currentPassword, $command->verificationToken, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        ?string $currentPassword,
        ?string $verificationToken,
        string $correlationId,
    ): int
    {
        if ($currentPassword === null && $verificationToken === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The request is invalid.', ['current_password' => ['Exactly one step-up proof is required.']]);
        }
        return $this->transactions->run(function () use ($actor, $currentPassword, $verificationToken, $correlationId): int {
            if ($currentPassword !== null) {
                $credential = $this->credentials->findForUser($actor->userId, lock: true);
                if ($credential === null || !password_verify($currentPassword, (string) $credential->password_hash)) {
                    throw new ApiException(ApiErrorCode::InvalidCredentials, 401, 'Invalid credentials.');
                }
            } else {
                $this->verificationProofConsumer->consumeVerificationToken((string) $verificationToken, 'PASSWORD_RESET', $actor->userId);
            }
            $count = $this->sessionLifecycle->revokeUserSessions($actor->userId, 'LOGOUT_ALL');
            $this->audit->write($actor->hqId, $actor->userId, 'AUTH_LOGOUT_ALL', 'USER', $actor->userId, $correlationId);
            return $count;
        });
    }
}
