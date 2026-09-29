<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Contracts;

use Modules\Notification\Domain\Enums\DeliveryChannel;

interface NotificationGatewayInterface
{
    public function send(
        string $eventId,
        DeliveryChannel $channel,
        string $recipientFingerprint,
        string $templateCode,
        string $secret,
    ): string;
}
