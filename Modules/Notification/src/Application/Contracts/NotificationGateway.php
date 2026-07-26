<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Contracts;

interface NotificationGateway
{
    public function send(
        string $eventId,
        string $channel,
        string $recipientFingerprint,
        string $templateCode,
        string $secret,
    ): string;
}
