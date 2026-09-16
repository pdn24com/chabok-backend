<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Contracts;

interface WorkerRuntime
{
    public function workerId(): string;

    public function heartbeat(int $ttl, string $at): void;

    public function shareContext(array $context): void;

    public function report(\Throwable $exception): void;

    public function clearContext(): void;
}
