<?php

declare(strict_types=1);

namespace Modules\Notification\Application\UseCases\DeliverNotification;

final readonly class DeliverNotificationCommand
{
    public function __construct(public array $event)
    {
    }
}
