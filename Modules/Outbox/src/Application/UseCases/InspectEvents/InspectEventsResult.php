<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\InspectEvents;

final readonly class InspectEventsResult
{
    public function __construct(public array $data)
    {
    }
}
