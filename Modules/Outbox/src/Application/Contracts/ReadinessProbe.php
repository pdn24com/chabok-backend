<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Contracts;

interface ReadinessProbe
{
    public function database(): void;

    public function redis(): bool;

    public function hasWorkerHeartbeat(): bool;

    public function warning(string $component, \Throwable $exception): void;
}
