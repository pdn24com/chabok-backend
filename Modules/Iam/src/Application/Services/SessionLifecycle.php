<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Iam\Application\Contracts\SessionLifecycleInterface;
use Modules\Iam\Application\Contracts\SessionRegistryInterface;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;

final readonly class SessionLifecycle implements SessionLifecycleInterface
{
    public function __construct(
        private ClockInterface $clock,
        private SessionRegistryInterface $sessionRegistry,
        private SessionRepositoryInterface $sessionRepository,
    ) {}

    public function revokeSession(string $sessionId, string $reason): void
    {
        $this->sessionRepository->reviseLiveSession($sessionId, $this->revocation($reason));
        $this->sessionRegistry->invalidate($sessionId);
    }

    public function revokeFamily(string $familyId, string $reason): void
    {
        $this->revoke($this->sessionRepository->liveIdsForFamily($familyId), $reason);
    }

    public function revokeUserSessions(string $userId, string $reason): int
    {
        return $this->revoke($this->sessionRepository->liveIdsForUser($userId), $reason);
    }

    public function revokeOtherSessions(string $userId, string $currentSessionId, string $reason): void
    {
        $this->revoke($this->sessionRepository->liveIdsForOtherSessions($userId, $currentSessionId), $reason);
    }

    /** @param list<string> $ids */
    private function revoke(array $ids, string $reason): int
    {
        if ($ids === []) {
            return 0;
        }
        $this->sessionRepository->reviseLiveSessions($ids, $this->revocation($reason));
        $this->sessionRegistry->invalidateMany($ids);

        return count($ids);
    }

    /** @return array<string, mixed> */
    private function revocation(string $reason): array
    {
        return ['revoked_at' => $this->clock->now(), 'revoked_reason' => $reason];
    }
}
