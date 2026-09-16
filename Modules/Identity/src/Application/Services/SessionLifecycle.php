<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Services;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Identity\Application\Contracts\SessionRegistry;
use Modules\Identity\Application\Repositories\SessionRepository;

final readonly class SessionLifecycle
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private Clock $clock,
        private SessionRegistry $sessionRegistry,
    )
    {
    }

    public function revokeSession(string $sessionId, string $reason): void
    {
        $this->sessionRepository->updateActive($sessionId, ['revoked_at' => $this->clock->now(), 'revoked_reason' => $reason, 'updated_at' => $this->clock->now()]);
        $this->sessionRegistry->invalidate($sessionId);
    }

    public function revokeFamily(string $familyId, string $reason): void
    {
        $sessions = $this->sessionRepository->allForFamily($familyId);
        foreach ($sessions as $session) {
            $this->revokeSession((string) $session->session_id, $reason);
        }
    }

    public function revokeUserSessions(string $userId, string $reason): int
    {
        $sessions = $this->sessionRepository->activeForUser($userId);
        foreach ($sessions as $session) {
            $this->revokeSession((string) $session->session_id, $reason);
        }
        return count($sessions);
    }
}
