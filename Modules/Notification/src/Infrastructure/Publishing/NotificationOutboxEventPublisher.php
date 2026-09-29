<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Publishing;

use Modules\Foundation\Application\Dto\OutboxEventDto;
use Modules\Foundation\Application\Dto\PublicationReceiptDto;
use Modules\Foundation\Application\Ports\OutboxEventPublisherInterface;
use Modules\Notification\Application\UseCases\DeliverNotification\DeliverNotificationCommand;
use Modules\Notification\Application\UseCases\DeliverNotification\DeliverNotificationHandler;

final readonly class NotificationOutboxEventPublisher implements OutboxEventPublisherInterface
{
    public function __construct(private DeliverNotificationHandler $deliverNotificationHandler) {}

    public function publish(OutboxEventDto $event): PublicationReceiptDto
    {
        return $this->deliverNotificationHandler->handle(new DeliverNotificationCommand($event));
    }
}
