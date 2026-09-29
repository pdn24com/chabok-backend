<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\ReplayEvent;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use LogicException;
use Modules\Outbox\Application\Repositories\OutboxEventRepositoryInterface;
use Modules\Outbox\Domain\Enums\PublicationState;

final readonly class ReplayEventHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private OutboxEventRepositoryInterface $outboxEventRepository,
    ) {}

    public function handle(ReplayEventCommand $command): ReplayEventResult
    {
        $eventId = $command->eventId;
        $this->connection->transaction(function () use ($eventId): void {
            $event = $this->outboxEventRepository->lockById($eventId);
            if ($event === null) {
                throw new InvalidArgumentException('Outbox event not found.');
            }
            if (! in_array($event->publication_state, [PublicationState::FAILED, PublicationState::DEAD_LETTER], true)) {
                throw new LogicException('Only failed or dead-lettered events can be replayed.');
            }
            $this->outboxEventRepository->apply($event, [
                'publication_state' => PublicationState::PENDING,
                'attempts' => 0,
                'next_attempt_at' => null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
                'last_failure_code' => null,
                'dead_lettered_at' => null,
            ]);
        }, attempts: 1);

        return new ReplayEventResult;
    }
}
