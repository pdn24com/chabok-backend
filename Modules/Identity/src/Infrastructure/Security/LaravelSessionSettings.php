<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Security;

use Modules\Identity\Application\Contracts\SessionSettings;

final class LaravelSessionSettings implements SessionSettings
{
    public function refreshTtlSeconds(): int
    {
        return (int) config('chabok.refresh_cookie.ttl_seconds');
    }
}
