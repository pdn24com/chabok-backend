<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Contracts;

use Throwable;

interface ReadinessProbeInterface
{
    public function database(): void;

    public function redis(): bool;

    public function hasWorkerHeartbeat(): bool;

    public function warning(string $component, Throwable $exception): void;
}
