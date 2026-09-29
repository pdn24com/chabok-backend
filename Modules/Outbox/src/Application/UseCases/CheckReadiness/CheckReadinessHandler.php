<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\CheckReadiness;

use Modules\Outbox\Application\Contracts\ReadinessProbeInterface;
use Modules\Outbox\Domain\Enums\ComponentHealth;
use Throwable;

final readonly class CheckReadinessHandler
{
    public function __construct(private ReadinessProbeInterface $readinessProbe) {}

    public function handle(CheckReadinessCommand $command): CheckReadinessResult
    {
        $mysql = ComponentHealth::DOWN;
        $redis = ComponentHealth::DOWN;
        $worker = ComponentHealth::STALE;
        try {
            $this->readinessProbe->database();
            $mysql = ComponentHealth::UP;
        } catch (Throwable $exception) {
            $this->readinessProbe->warning('mysql', $exception);
        }
        try {
            if ($this->readinessProbe->redis()) {
                $redis = ComponentHealth::UP;
            }
            if ($this->readinessProbe->hasWorkerHeartbeat()) {
                $worker = ComponentHealth::UP;
            }
        } catch (Throwable $exception) {
            $this->readinessProbe->warning('redis', $exception);
        }

        return new CheckReadinessResult($mysql, $redis, $worker);
    }
}
