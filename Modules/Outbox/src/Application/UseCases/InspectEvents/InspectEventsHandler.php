<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\InspectEvents;

use Modules\Outbox\Application\Repositories\OutboxEventRepositoryInterface;

final readonly class InspectEventsHandler
{
    /** An operator inspection never returns more than this many events at once. */
    private const MAX_EVENTS = 250;

    public function __construct(private OutboxEventRepositoryInterface $outboxEventRepository) {}

    public function handle(InspectEventsCommand $command): InspectEventsResult
    {
        return new InspectEventsResult($this->outboxEventRepository->recentSummaries($command->state, min(self::MAX_EVENTS, $command->limit)));
    }
}
