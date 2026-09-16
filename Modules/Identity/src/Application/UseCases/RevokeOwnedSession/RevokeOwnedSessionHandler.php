<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\RevokeOwnedSession;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Repositories\SessionRepository;
use Modules\Identity\Application\Services\SessionLifecycle;

final readonly class RevokeOwnedSessionHandler
{
    public function __construct(
        private TransactionManager $transactions,
        private SessionRepository $sessionRepository,
        private SessionLifecycle $sessionLifecycle,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(RevokeOwnedSessionCommand $command): RevokeOwnedSessionResult
    {
        $this->execute($command->actor, $command->sessionId, $command->correlationId);
        return new RevokeOwnedSessionResult();
    }

    private function execute(AuthenticatedPrincipal $actor, string $sessionId, string $correlationId): void
    {
        $this->transactions->run(function () use ($actor, $sessionId, $correlationId): void {
            $session = $this->sessionRepository->findOwnedForUpdate($actor->userId, $sessionId);
            if ($session === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $this->sessionLifecycle->revokeSession($sessionId, 'USER_REVOKED');
            $this->audit->write($actor->hqId, $actor->userId, 'SESSION_REVOKED', 'SESSION', $sessionId, $correlationId);
        });
    }
}
