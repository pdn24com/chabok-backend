<?php

declare(strict_types=1);

namespace Modules\Notification\Application\UseCases\DeliverNotification;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Dto\OutboxEventDto;
use Modules\Foundation\Application\Dto\PublicationReceiptDto;
use Modules\Foundation\Application\Exceptions\OutboxPublishException;
use Modules\Iam\Application\Repositories\OtpChallengeRepositoryInterface;
use Modules\Notification\Application\Contracts\DeliveryCipherInterface;
use Modules\Notification\Application\Contracts\NotificationGatewayInterface;
use Modules\Notification\Application\Dto\DeliveryContextDto;
use Modules\Notification\Application\Repositories\NotificationDeliveryRepositoryInterface;
use Modules\Notification\Domain\Enums\DeliveryChannel;

final readonly class DeliverNotificationHandler
{
    public function __construct(
        private NotificationGatewayInterface $notificationGateway,
        private DeliveryCipherInterface $deliveryCipher,
        private ClockInterface $clock,
        private NotificationDeliveryRepositoryInterface $notificationDeliveryRepository,
        private OtpChallengeRepositoryInterface $otpChallengeRepository,
    ) {}

    public function handle(DeliverNotificationCommand $command): PublicationReceiptDto
    {
        $event = $command->event;
        $existing = $this->notificationDeliveryRepository->findByEvent($event->eventId);
        if ($existing !== null) {
            return new PublicationReceiptDto($existing->provider_code, $existing->provider_message_id);
        }
        if (! in_array($event->eventType, ['identity.otp.delivery.requested', 'identity.invitation.delivery.requested'], true)) {
            return new PublicationReceiptDto('local-domain-event-v1', hash('sha256', 'local-domain-event-v1|'.$event->eventId));
        }
        $payload = $event->payload;
        $ciphertext = $payload['delivery_ciphertext'] ?? null;
        if (! is_string($ciphertext)) {
            throw new OutboxPublishException('DELIVERY_SECRET_MISSING', false);
        }
        $secret = $this->deliveryCipher->decrypt($ciphertext);
        $context = $this->deliveryContext($event);
        try {
            $providerMessageId = $this->notificationGateway->send($event->eventId, $context->channel, $context->recipientFingerprint, $context->templateCode, $secret);
        } finally {
            $secret = '';
        }
        $providerMessageId = $this->recordReceipt($event, $context, $providerMessageId);

        return new PublicationReceiptDto('deterministic-v1', $providerMessageId);
    }

    private function deliveryContext(OutboxEventDto $event): DeliveryContextDto
    {
        if ($event->eventType === 'identity.invitation.delivery.requested') {
            return new DeliveryContextDto(DeliveryChannel::from($event->payload['channel']), $event->payload['recipient_fingerprint'], 'IAM_INVITATION');
        }
        $challenge = $this->otpChallengeRepository->findWithUser($event->aggregateId);
        if ($challenge === null) {
            throw new OutboxPublishException('OTP_CHALLENGE_NOT_FOUND', false);
        }
        $channel = $challenge->user?->normalized_email !== null && $challenge->user?->normalized_mobile === null ? DeliveryChannel::EMAIL : DeliveryChannel::SMS;

        return new DeliveryContextDto($channel, $challenge->destination_fingerprint, 'IAM_OTP_'.$challenge->purpose->value);
    }

    private function recordReceipt(OutboxEventDto $event, DeliveryContextDto $context, string $providerMessageId): string
    {
        return $this->notificationDeliveryRepository->recordReceipt([
            'hq_id' => $event->hqId, 'event_id' => $event->eventId,
            'channel' => $context->channel->value, 'recipient_fingerprint' => $context->recipientFingerprint,
            'template_code' => $context->templateCode, 'provider_code' => 'deterministic-v1',
            'provider_message_id' => $providerMessageId, 'status' => 'SENT',
            'correlation_id' => $event->correlationId, 'sent_at' => $this->clock->now(),
        ]);
    }
}
