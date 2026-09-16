<?php

declare(strict_types=1);

namespace Modules\Outbox\Application;

final readonly class OutboxProcessor
{
    public function __construct(
        private \Modules\Outbox\Application\UseCases\PublishPendingEvents\PublishPendingEventsHandler $publishPendingEvents,
        private \Modules\Outbox\Application\UseCases\ReplayEvent\ReplayEventHandler $replayEvent,
    )
    {
    }

    public function runOnce(int $limit = 25, ?string $workerId = null): array
    {
        return $this->publishPendingEvents->handle(new \Modules\Outbox\Application\UseCases\PublishPendingEvents\PublishPendingEventsCommand($limit, $workerId))->data;
    }

    public function replay(string $eventId): void
    {
        $this->replayEvent->handle(new \Modules\Outbox\Application\UseCases\ReplayEvent\ReplayEventCommand($eventId));
    }
}
