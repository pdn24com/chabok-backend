<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\CheckReadiness;

use Modules\Outbox\Domain\Enums\ComponentHealth;

final readonly class CheckReadinessResult
{
    public bool $ready;

    public function __construct(
        public ComponentHealth $mysql,
        public ComponentHealth $redis,
        public ComponentHealth $outboxWorker,
    ) {
        $this->ready = $mysql === ComponentHealth::UP && $redis === ComponentHealth::UP && $outboxWorker === ComponentHealth::UP;
    }
}
