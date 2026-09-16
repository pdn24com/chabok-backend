<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\InspectEvents;

final readonly class InspectEventsHandler
{
    public function __construct(private \Modules\Outbox\Application\Repositories\OutboxEventRepository $events)
    {
    }

    public function handle(InspectEventsCommand $command): InspectEventsResult
    {
        return new InspectEventsResult($this->events->inspect($command->state ? strtoupper($command->state) : null, min(250, $command->limit)));
    }
}
