<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Contracts;

interface OutboxSettings
{
    public function heartbeatTtl(): int;

    public function claimTimeout(): int;

    public function maxAttempts(): int;

    public function maxBackoff(): int;

    public function baseBackoff(): int;
}
