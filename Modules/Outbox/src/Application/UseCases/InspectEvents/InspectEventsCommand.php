<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\InspectEvents;

final readonly class InspectEventsCommand
{
    public function __construct(public ?string $state = null, public int $limit = 50)
    {
    }
}
