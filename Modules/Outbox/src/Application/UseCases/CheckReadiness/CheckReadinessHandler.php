<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\CheckReadiness;

final readonly class CheckReadinessHandler
{
    public function __construct(private \Modules\Outbox\Application\Contracts\ReadinessProbe $probe)
    {
    }

    public function handle(CheckReadinessCommand $command): CheckReadinessResult
    {
        return new CheckReadinessResult($this->execute());
    }

    private function execute(): array
    {
        $components = ['mysql' => 'DOWN', 'redis' => 'DOWN', 'outbox_worker' => 'STALE'];
        try {
            $this->probe->database();
            $components['mysql'] = 'UP';
        } catch (\Throwable $exception) {
            $this->probe->warning('mysql', $exception);
        }
        try {
            if ($this->probe->redis()) {
                $components['redis'] = 'UP';
            }
            if ($this->probe->hasWorkerHeartbeat()) {
                $components['outbox_worker'] = 'UP';
            }
        } catch (\Throwable $exception) {
            $this->probe->warning('redis', $exception);
        }
        return [
            'ready' => !in_array('DOWN', $components, true) && $components['outbox_worker'] === 'UP',
            'components' => $components,
        ];
    }
}
