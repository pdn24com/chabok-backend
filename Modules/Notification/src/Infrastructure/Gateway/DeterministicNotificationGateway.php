<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Gateway;

use Modules\Notification\Application\Contracts\NotificationGateway;
use Modules\Foundation\Application\OutboxPublishException;

final class DeterministicNotificationGateway implements NotificationGateway
{
    public function send(
        string $eventId,
        string $channel,
        string $recipientFingerprint,
        string $templateCode,
        string $secret,
    ): string {
        if ($secret === '') {
            throw new OutboxPublishException('DELIVERY_SECRET_MISSING', false);
        }
        if (in_array($eventId, (array) config('chabok.notifications.fail_event_ids', []), true)) {
            throw new OutboxPublishException('DETERMINISTIC_PROVIDER_FAILURE');
        }

        return hash('sha256', implode('|', [
            'deterministic-v1', $eventId, $channel, $recipientFingerprint, $templateCode,
        ]));
    }
}
