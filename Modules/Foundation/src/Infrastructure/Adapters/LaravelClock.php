<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Adapters;

use DateTimeImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\ValueObjects\Instant;

final class LaravelClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return Instant::createFromInterface(now());
    }
}
