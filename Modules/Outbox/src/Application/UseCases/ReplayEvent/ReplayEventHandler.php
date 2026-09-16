<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\ReplayEvent;

final readonly class ReplayEventHandler
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Outbox\Application\Repositories\OutboxEventRepository $events,
    )
    {
    }

    public function handle(ReplayEventCommand $command): ReplayEventResult
    {
        $this->execute($command->eventId);
        return new ReplayEventResult();
    }

    private function execute(string $eventId): void
    {
        $this->transactions->run(function () use ($eventId): void {
            $event = $this->events->lockEvent($eventId);
            if ($event === null) {
                throw new \InvalidArgumentException('Outbox event not found.');
            }
            if (!in_array($event->publication_state, ['FAILED', 'DEAD_LETTER'], true)) {
                throw new \LogicException('Only failed or dead-lettered events can be replayed.');
            }
            $this->events->update($eventId, [
                'publication_state' => 'PENDING',
                'attempts' => 0,
                'next_attempt_at' => null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
                'last_failure_code' => null,
                'dead_lettered_at' => null,
            ]);
        });
    }
}
