<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Modules\Foundation\Application\Support\SensitiveDataRedactor;
use Modules\Outbox\Application\Contracts\ReadinessProbeInterface;
use Throwable;

final class LaravelReadinessProbe implements ReadinessProbeInterface
{
    public function database(): void
    {
        DB::connection('mysql-health')->selectOne('SELECT 1 AS healthy');
    }

    public function redis(): bool
    {
        return (bool) Redis::connection('health')->ping();
    }

    public function hasWorkerHeartbeat(): bool
    {
        return Redis::connection('health')->get('chabok:outbox:heartbeat') !== null;
    }

    public function warning(string $component, Throwable $exception): void
    {
        Log::warning('readiness_component_down', [
            'component' => $component,
            'exception_class' => $exception::class,
            'reason' => SensitiveDataRedactor::message($exception->getMessage()),
        ]);
    }
}
