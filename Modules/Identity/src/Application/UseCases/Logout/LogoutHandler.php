<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\Logout;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Identity\Application\Repositories\SessionRepository;
use Modules\Identity\Application\Services\SessionLifecycle;
use Modules\Identity\Domain\OpaqueToken;

final readonly class LogoutHandler
{
    public function __construct(
        private TransactionManager $transactions,
        private SessionRepository $sessionRepository,
        private SessionLifecycle $sessionLifecycle,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(LogoutCommand $command): LogoutResult
    {
        $this->execute($command->rawToken, $command->correlationId);
        return new LogoutResult();
    }

    private function execute(?string $rawToken, string $correlationId): void
    {
        if ($rawToken === null || $rawToken === '') {
            return;
        }
        $this->transactions->run(function () use ($rawToken, $correlationId): void {
            $session = $this->sessionRepository->findByRefreshHashForUpdate(OpaqueToken::hash($rawToken));
            if ($session === null) {
                return;
            }
            $this->sessionLifecycle->revokeSession((string) $session->session_id, 'LOGOUT');
            $this->audit->write($session->hq_id === null ? null : (string) $session->hq_id, (string) $session->user_id, 'AUTH_LOGOUT', 'SESSION', (string) $session->session_id, $correlationId);
        });
    }
}
