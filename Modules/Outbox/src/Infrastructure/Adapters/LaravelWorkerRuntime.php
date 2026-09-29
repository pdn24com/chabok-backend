<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Modules\Outbox\Application\Contracts\WorkerRuntimeInterface;
use Throwable;

final class LaravelWorkerRuntime implements WorkerRuntimeInterface
{
    public function workerId(): string
    {
        return gethostname().':'.getmypid();
    }

    public function heartbeat(int $ttl, string $at): void
    {
        Redis::connection('cache')->setex('chabok:outbox:heartbeat', $ttl, $at);
    }

    public function shareContext(array $context): void
    {
        Log::shareContext($context);
    }

    public function report(Throwable $exception): void
    {
        report($exception);
    }

    public function clearContext(): void
    {
        Log::flushSharedContext();
    }
}
