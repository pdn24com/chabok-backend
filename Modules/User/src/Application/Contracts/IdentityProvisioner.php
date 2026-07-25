<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

interface IdentityProvisioner
{
    public function provisionPassword(string $userId, string $password): void;

    /**
     * @param array<string, mixed> $user
     */
    public function createInvitation(
        array $user,
        string $channel,
        ?string $actorId,
        string $correlationId,
    ): void;

    public function revokeUserSessions(string $userId, string $reason): int;
}
