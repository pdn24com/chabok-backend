<?php

declare(strict_types=1);

namespace Modules\Notification\Application\UseCases\DeliverNotification;

use Modules\Foundation\Application\OutboxPublishException;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Notification\Application\Contracts\NotificationGateway;
use Modules\Notification\Application\Contracts\DeliveryCipher;
use Modules\Notification\Application\Repositories\NotificationDeliveryRepository;

final readonly class DeliverNotificationHandler
{
    public function __construct(
        private NotificationGateway $gateway,
        private NotificationDeliveryRepository $deliveries,
        private DeliveryCipher $cipher,
        private Clock $clock,
        private IdentifierGenerator $identifiers,
    )
    {
    }

    public function handle(DeliverNotificationCommand $command): DeliverNotificationResult
    {
        return new DeliverNotificationResult($this->publish($command->event));
    }

    private function publish(array $event): array
    {
        $existing = $this->deliveries->find($event['event_id']);
        if ($existing !== null) {
            return ['provider' => (string) $existing->provider_code, 'receipt' => (string) $existing->provider_message_id];
        }
        if (!in_array($event['event_type'], ['identity.otp.delivery.requested', 'identity.invitation.delivery.requested'], true)) {
            return [
                'provider' => 'local-domain-event-v1',
                'receipt' => hash('sha256', 'local-domain-event-v1|' . $event['event_id']),
            ];
        }
        $payload = $event['payload'];
        $ciphertext = $payload['delivery_ciphertext'] ?? null;
        if (!is_string($ciphertext)) {
            throw new OutboxPublishException('DELIVERY_SECRET_MISSING', false);
        }
        $secret = $this->cipher->decrypt($ciphertext);
        [$channel, $fingerprint, $template] = $this->deliveryContext($event);
        try {
            $providerMessageId = $this->gateway->send($event['event_id'], $channel, $fingerprint, $template, $secret);
        } finally {
            $secret = '';
        }
        $providerMessageId = $this->deliveries->recordReceipt([
            'delivery_id' => $this->identifiers->uuid(),
            'hq_id' => $event['hq_id'],
            'event_id' => $event['event_id'],
            'channel' => $channel,
            'recipient_fingerprint' => $fingerprint,
            'template_code' => $template,
            'provider_code' => 'deterministic-v1',
            'provider_message_id' => $providerMessageId,
            'status' => 'SENT',
            'correlation_id' => $event['correlation_id'],
            'sent_at' => $this->clock->now(),
            'created_at' => $this->clock->now(),
        ]);
        return ['provider' => 'deterministic-v1', 'receipt' => $providerMessageId];
    }

    private function deliveryContext(array $event): array
    {
        if ($event['event_type'] === 'identity.invitation.delivery.requested') {
            return [(string) $event['payload']['channel'], (string) $event['payload']['recipient_fingerprint'], 'IAM_INVITATION'];
        }
        $challenge = $this->deliveries->challengeContext($event['aggregate_id']);
        if ($challenge === null) {
            throw new OutboxPublishException('OTP_CHALLENGE_NOT_FOUND', false);
        }
        $channel = $challenge->normalized_email !== null && $challenge->normalized_mobile === null ? 'EMAIL' : 'SMS';
        return [$channel, (string) $challenge->destination_fingerprint, 'IAM_OTP_' . (string) $challenge->purpose];
    }
}
