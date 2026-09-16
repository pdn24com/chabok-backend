<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Illuminate\Support\Facades\Redis;
use Modules\Authorization\Application\Contracts\AuthorizationCache;

final class RedisAuthorizationCache implements AuthorizationCache
{
    public function get(string $key): ?string
    {
        $value = Redis::connection('cache')->get($key);
        return is_string($value) ? $value : null;
    }

    public function put(string $key, int $ttl, string $context): void
    {
        Redis::connection('cache')->setex($key, $ttl, $context);
    }

    public function forget(string $key): void
    {
        Redis::connection('cache')->del($key);
    }
}
