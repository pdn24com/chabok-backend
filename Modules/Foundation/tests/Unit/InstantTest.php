<?php

declare(strict_types=1);

namespace Modules\Foundation\Tests\Unit;

use Modules\Foundation\Domain\Instant;
use PHPUnit\Framework\TestCase;

final class InstantTest extends TestCase
{
    public function test_snapshot_serialization_preserves_utc_and_microseconds(): void
    {
        $instant = Instant::createFromInterface(new \DateTimeImmutable('2026-09-16T12:30:45.123456+03:30'));
        self::assertSame('"2026-09-16T09:00:45.123456Z"', json_encode($instant, JSON_THROW_ON_ERROR));
        self::assertSame('12:30:45.123456', $instant->format('H:i:s.u'));
    }
}
