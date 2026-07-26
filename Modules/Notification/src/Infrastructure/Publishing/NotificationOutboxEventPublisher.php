<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Publishing;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\OutboxEventPublisher;
use Modules\Notification\Application\Contracts\NotificationGateway;
use Modules\Foundation\Application\OutboxPublishException;

final readonly class NotificationOutboxEventPublisher implements OutboxEventPublisher
{
    public function __construct(private NotificationGateway $gateway) {}

    public function publish(array $event): array
    {
        $existing = DB::table('notification_deliveries')->where('event_id', $event['event_id'])->first();
        if ($existing !== null) {
            return [
                'provider' => (string) $existing->provider_code,
                'receipt' => (string) $existing->provider_message_id,
            ];
        }
        if (! in_array($event['event_type'], [
            'identity.otp.delivery.requested',
            'identity.invitation.delivery.requested',
        ], true)) {
            return [
                'provider' => 'local-domain-event-v1',
                'receipt' => hash('sha256', 'local-domain-event-v1|'.$event['event_id']),
            ];
        }

        $payload = $event['payload'];
        $ciphertext = $payload['delivery_ciphertext'] ?? null;
        if (! is_string($ciphertext)) {
            throw new OutboxPublishException('DELIVERY_SECRET_MISSING', false);
        }
        try {
            $secret = Crypt::decryptString($ciphertext);
        } catch (\Throwable) {
            throw new OutboxPublishException('DELIVERY_SECRET_INVALID', false);
        }
        [$channel, $fingerprint, $template] = $this->deliveryContext($event);
        try {
            $providerMessageId = $this->gateway->send(
                $event['event_id'],
                $channel,
                $fingerprint,
                $template,
                $secret,
            );
        } finally {
            $secret = '';
        }

        try {
            DB::table('notification_deliveries')->insert([
                'delivery_id' => (string) Str::uuid(),
                'hq_id' => $event['hq_id'],
                'event_id' => $event['event_id'],
                'channel' => $channel,
                'recipient_fingerprint' => $fingerprint,
                'template_code' => $template,
                'provider_code' => 'deterministic-v1',
                'provider_message_id' => $providerMessageId,
                'status' => 'SENT',
                'correlation_id' => $event['correlation_id'],
                'sent_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $existing = DB::table('notification_deliveries')->where('event_id', $event['event_id'])->first();
            if ($existing === null) {
                throw $exception;
            }
            $providerMessageId = (string) $existing->provider_message_id;
        }

        return ['provider' => 'deterministic-v1', 'receipt' => $providerMessageId];
    }

    /** @return array{string, string, string} */
    private function deliveryContext(array $event): array
    {
        if ($event['event_type'] === 'identity.invitation.delivery.requested') {
            return [
                (string) $event['payload']['channel'],
                (string) $event['payload']['recipient_fingerprint'],
                'IAM_INVITATION',
            ];
        }
        $challenge = DB::table('otp_challenges as o')
            ->leftJoin('users as u', 'u.user_id', '=', 'o.user_id')
            ->where('o.challenge_id', $event['aggregate_id'])
            ->first([
                'o.destination_fingerprint', 'o.purpose',
                'u.normalized_mobile', 'u.normalized_email',
            ]);
        if ($challenge === null) {
            throw new OutboxPublishException('OTP_CHALLENGE_NOT_FOUND', false);
        }
        $channel = $challenge->normalized_email !== null && $challenge->normalized_mobile === null
            ? 'EMAIL'
            : 'SMS';

        return [
            $channel,
            (string) $challenge->destination_fingerprint,
            'IAM_OTP_'.(string) $challenge->purpose,
        ];
    }
}
