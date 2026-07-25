<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

interface UserSessionManager
{
    /** @return list<array<string, mixed>> */
    public function listSessions(string $userId): array;

    public function revokeUserSessions(string $userId, string $reason): int;
}
