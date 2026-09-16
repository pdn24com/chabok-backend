<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Services;

use Modules\Foundation\Application\Contracts\Clock;

final class LaravelClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return \Modules\Foundation\Domain\Instant::createFromInterface(now());
    }
}
