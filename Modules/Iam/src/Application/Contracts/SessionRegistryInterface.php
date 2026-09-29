<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

interface SessionRegistryInterface
{
    public function invalidate(string $sessionId, int $ttl = 2592000): void;

    /** @param list<string> $sessionIds */
    public function invalidateMany(array $sessionIds, int $ttl = 2592000): void;

    public function isInvalidated(string $sessionId): bool;

    public function rememberRotatedToken(
        string $tokenHash,
        string $familyId,
        int $ttl,
    ): void;

    public function rotatedFamily(string $tokenHash): ?string;
}
