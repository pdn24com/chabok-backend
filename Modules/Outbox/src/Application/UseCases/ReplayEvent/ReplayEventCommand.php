<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\ReplayEvent;

final readonly class ReplayEventCommand
{
    public function __construct(public string $eventId)
    {
    }
}
