<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\PublishPendingEvents;

final readonly class PublishPendingEventsResult
{
    public function __construct(
        public int $claimed,
        public int $published,
        public int $failed,
        public int $deadLettered,
    ) {}
}
