<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;

final class SecurityMetricsCommand extends Command
{
    protected $signature = 'chabok:security-metrics {metric}';

    protected $description = 'Read a safe aggregated security metric without identifiers.';

    public function handle(SecurityMetricRecorder $metrics): int
    {
        $snapshot = $metrics->snapshot((string) $this->argument('metric'));
        $rows = [];
        foreach ($snapshot as $labels => $count) {
            $rows[] = ['labels' => $labels, 'count' => $count];
        }
        $this->table(['labels', 'count'], $rows);

        return self::SUCCESS;
    }
}
