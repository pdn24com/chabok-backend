<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Publishing;

use Modules\Foundation\Application\Contracts\OutboxEventPublisher;
use Modules\Foundation\Application\OutboxPublishException;

final class FailClosedOutboxEventPublisher implements OutboxEventPublisher
{
    public function publish(array $event): array
    {
        throw new OutboxPublishException('PUBLISHER_UNAVAILABLE');
    }
}
