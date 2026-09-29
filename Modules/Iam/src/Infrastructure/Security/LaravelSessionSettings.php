<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Security;

use Modules\Iam\Application\Contracts\SessionSettingsInterface;

final class LaravelSessionSettings implements SessionSettingsInterface
{
    public function refreshTtlSeconds(): int
    {
        return (int) config('chabok.refresh_cookie.ttl_seconds');
    }
}
