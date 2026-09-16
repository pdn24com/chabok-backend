<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

interface AuthorizationCache
{
    public function get(string $key): ?string;

    public function put(string $key, int $ttl, string $context): void;

    public function forget(string $key): void;
}
