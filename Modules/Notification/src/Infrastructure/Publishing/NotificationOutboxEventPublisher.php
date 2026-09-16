<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Publishing;

use Modules\Foundation\Application\Contracts\OutboxEventPublisher;
use Modules\Notification\Application\UseCases\DeliverNotification\DeliverNotificationCommand;
use Modules\Notification\Application\UseCases\DeliverNotification\DeliverNotificationHandler;

final readonly class NotificationOutboxEventPublisher implements OutboxEventPublisher
{
    public function __construct(private DeliverNotificationHandler $delivery)
    {
    }

    public function publish(array $event): array
    {
        return $this->delivery->handle(new DeliverNotificationCommand($event))->data;
    }
}
