<?php

declare(strict_types=1);

namespace Modules\Notification\Application\UseCases\DeliverNotification;

use Modules\Foundation\Application\Dto\OutboxEventDto;

final readonly class DeliverNotificationCommand
{
    public function __construct(public OutboxEventDto $event) {}
}
