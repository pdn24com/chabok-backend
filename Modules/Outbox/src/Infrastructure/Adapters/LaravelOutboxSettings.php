<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Adapters;

use Modules\Outbox\Application\Contracts\OutboxSettings;

final class LaravelOutboxSettings implements OutboxSettings
{
    public function heartbeatTtl(): int
    {
        return (int) config('chabok.outbox.heartbeat_ttl_seconds', 180);
    }

    public function claimTimeout(): int
    {
        return (int) config('chabok.outbox.claim_timeout_seconds', 120);
    }

    public function maxAttempts(): int
    {
        return (int) config('chabok.outbox.max_attempts', 5);
    }

    public function maxBackoff(): int
    {
        return (int) config('chabok.outbox.max_backoff_seconds', 900);
    }

    public function baseBackoff(): int
    {
        return (int) config('chabok.outbox.base_backoff_seconds', 5);
    }
}
