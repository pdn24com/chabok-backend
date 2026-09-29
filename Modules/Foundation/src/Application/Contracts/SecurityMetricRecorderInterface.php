<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface SecurityMetricRecorderInterface
{
    /** @param array<string, string> $labels */
    public function increment(string $metric, array $labels = []): void;

    /** @return array<string, int> */
    public function snapshot(string $metric): array;
}
