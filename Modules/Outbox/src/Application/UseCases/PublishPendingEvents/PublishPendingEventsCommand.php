<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\PublishPendingEvents;

final readonly class PublishPendingEventsCommand
{
    public function __construct(public int $limit = 25, public ?string $workerId = null)
    {
    }
}
