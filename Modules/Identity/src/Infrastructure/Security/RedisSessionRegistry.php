<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Security;

use Illuminate\Support\Facades\Redis;
use Modules\Identity\Application\Contracts\SessionRegistry;

final class RedisSessionRegistry implements SessionRegistry
{
    public function invalidate(string $sessionId, int $ttl = 2592000): void
    {
        Redis::connection()->setex("iam:session:revoked:{$sessionId}", $ttl, '1');
    }

    public function isInvalidated(string $sessionId): bool
    {
        return Redis::connection()->exists("iam:session:revoked:{$sessionId}") > 0;
    }

    public function rememberRotatedToken(string $tokenHash, string $familyId, int $ttl): void
    {
        Redis::connection()->setex("iam:refresh:rotated:{$tokenHash}", $ttl, $familyId);
    }

    public function rotatedFamily(string $tokenHash): ?string
    {
        $value = Redis::connection()->get("iam:refresh:rotated:{$tokenHash}");
        return is_string($value) && $value !== '' ? $value : null;
    }
}
