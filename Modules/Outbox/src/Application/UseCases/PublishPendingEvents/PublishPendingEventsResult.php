<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\PublishPendingEvents;

final readonly class PublishPendingEventsResult
{
    public function __construct(public array $data)
    {
    }
}
