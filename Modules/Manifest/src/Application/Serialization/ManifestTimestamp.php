<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Serialization;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ManifestTimestamp
{
    public static function format(DateTimeInterface|string|null $value): string
    {
        // Keep the established wire precision of cast model timestamps.
        $timestamp = $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;

        return CarbonImmutable::parse($timestamp, 'UTC')->utc()->toISOString();
    }
}
