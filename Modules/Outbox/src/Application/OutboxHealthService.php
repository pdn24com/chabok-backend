<?php

declare(strict_types=1);

namespace Modules\Outbox\Application;

final readonly class OutboxHealthService
{
    public function __construct(private \Modules\Outbox\Application\UseCases\CheckReadiness\CheckReadinessHandler $checkReadiness)
    {
    }

    public function readiness(): array
    {
        return $this->checkReadiness->handle(new \Modules\Outbox\Application\UseCases\CheckReadiness\CheckReadinessCommand())->data;
    }
}
