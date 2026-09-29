<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\RevokeOwnedSession;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;

final readonly class RevokeOwnedSessionHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private SessionRepositoryInterface $sessionRepository,
    ) {}

    public function handle(RevokeOwnedSessionCommand $command): RevokeOwnedSessionResult
    {
        $actor = $command->actor;
        $sessionId = $command->sessionId;
        $correlationId = $command->correlationId;
        $this->connection->transaction(function () use ($actor, $sessionId, $correlationId): void {
            $session = $this->sessionRepository->lockOwnedSession($actor->userId, $sessionId);
            if ($session === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $this->sessionLifecycle->revokeSession($sessionId, 'USER_REVOKED');
            $this->auditWriter->write($actor->hqId, $actor->userId, 'SESSION_REVOKED', 'SESSION', $sessionId, $correlationId);
        }, attempts: 3);

        return new RevokeOwnedSessionResult;
    }
}
