<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\Logout;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;
use Modules\Iam\Domain\Support\OpaqueToken;

final readonly class LogoutHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private SessionLifecycleInterface $sessionLifecycle,
        private AuditWriterInterface $auditWriter,
        private SessionRepositoryInterface $sessionRepository,
    ) {}

    public function handle(LogoutCommand $command): LogoutResult
    {
        $rawToken = $command->rawToken;
        $correlationId = $command->correlationId;
        if ($rawToken === null || $rawToken === '') {
            return new LogoutResult;
        }
        $this->connection->transaction(function () use ($rawToken, $correlationId): void {
            $session = $this->sessionRepository->lockByRefreshToken(OpaqueToken::hash($rawToken));
            if ($session === null) {
                return;
            }
            $this->sessionLifecycle->revokeSession((string) $session->session_id, 'LOGOUT');
            $this->auditWriter->write($session->hq_id === null ? null : (string) $session->hq_id, (string) $session->user_id, 'AUTH_LOGOUT', 'SESSION', (string) $session->session_id, $correlationId);
        }, attempts: 3);

        return new LogoutResult;
    }
}
