<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Observability;

use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorderInterface;

final class RedisSecurityMetricRecorder implements SecurityMetricRecorderInterface
{
    public function increment(string $metric, array $labels = []): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{2,79}$/', $metric)) {
            throw new InvalidArgumentException('Invalid security metric name.');
        }
        ksort($labels);
        foreach ($labels as $key => $value) {
            if (! preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) || ! preg_match('/^[A-Z0-9_.-]{1,80}$/', $value)) {
                throw new InvalidArgumentException('Security metric labels must be safe catalog values.');
            }
        }
        $field = $labels === [] ? 'total' : http_build_query($labels, '', ',');
        Redis::connection('cache')->hincrby("chabok:security:metric:{$metric}", $field, 1);
    }

    public function snapshot(string $metric): array
    {
        $values = Redis::connection('cache')->hgetall("chabok:security:metric:{$metric}");

        return array_map(static fn ($value): int => (int) $value, $values);
    }
}
