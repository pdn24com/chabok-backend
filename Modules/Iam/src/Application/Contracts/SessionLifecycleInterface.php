<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

interface SessionLifecycleInterface
{
    public function revokeSession(string $sessionId, string $reason): void;

    public function revokeOtherSessions(string $userId, string $currentSessionId, string $reason): void;

    public function revokeFamily(string $familyId, string $reason): void;

    public function revokeUserSessions(string $userId, string $reason): int;
}
