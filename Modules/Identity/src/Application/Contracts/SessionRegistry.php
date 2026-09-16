<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Contracts;

interface SessionRegistry
{
    public function invalidate(string $sessionId, int $ttl = 2592000): void;

    public function isInvalidated(string $sessionId): bool;

    public function rememberRotatedToken(string $tokenHash, string $familyId, int $ttl): void;

    public function rotatedFamily(string $tokenHash): ?string;
}
