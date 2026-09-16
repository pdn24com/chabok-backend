<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Services;

final readonly class PublicationRecorder
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Outbox\Application\Repositories\OutboxEventRepository $events,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Outbox\Application\Contracts\OutboxSettings $settings,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\SecurityMetricRecorder $metrics,
    )
    {
    }

    public function complete(string $eventId, string $claimToken, array $receipt): void
    {
        $this->transactions->run(function () use ($eventId, $claimToken, $receipt): void {
            $event = $this->events->lockClaim($eventId, $claimToken);
            if ($event === null) {
                throw new \LogicException('Outbox claim was lost before completion.');
            }
            $payload = json_decode((string) $event->payload, true, 512, JSON_THROW_ON_ERROR);
            $payload = $this->destroyDeliverySecret($payload);
            $payload['publication_receipt'] = ['provider' => $receipt['provider'], 'receipt' => $receipt['receipt']];
            $this->events->update($eventId, [
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'publication_state' => 'PUBLISHED',
                'published_at' => $this->clock->now(),
                'next_attempt_at' => null,
                'last_failure_code' => null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
            ]);
        });
    }

    public function fail(object $event, string $failureCode, bool $retryable): bool
    {
        $maxAttempts = $this->settings->maxAttempts();
        $deadLettered = !$retryable || (int) $event->attempts >= $maxAttempts;
        $this->transactions->run(function () use ($event, $failureCode, $deadLettered): void {
            $current = $this->events->lockClaim($event->event_id, $event->claim_token);
            if ($current === null) {
                return;
            }
            $delay = min($this->settings->maxBackoff(), $this->settings->baseBackoff() * 2 ** max(0, (int) $current->attempts - 1));
            $this->events->update($event->event_id, [
                'publication_state' => $deadLettered ? 'DEAD_LETTER' : 'FAILED',
                'next_attempt_at' => $deadLettered ? null : $this->clock->now()->modify('+' . $delay . ' seconds'),
                'last_failure_code' => mb_substr($failureCode, 0, 120),
                'dead_lettered_at' => $deadLettered ? $this->clock->now() : null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
            ]);
            if ($deadLettered) {
                $this->audit->write($current->hq_id === null ? null : (string) $current->hq_id, null, 'OUTBOX_EVENT_DEAD_LETTERED', 'OUTBOX_EVENT', (string) $current->event_id, (string) $current->correlation_id, safeNote: "Failure code: {$failureCode}", sourceClient: 'OUTBOX_WORKER');
            }
        });
        $this->metrics->increment($deadLettered ? 'outbox.dead_lettered' : 'outbox.retry_scheduled', ['code' => preg_replace('/[^A-Z0-9_.-]/', '_', strtoupper($failureCode)) ?: 'UNKNOWN']);
        return $deadLettered;
    }

    public function destroyDeliverySecret(array $payload): array
    {
        if (array_key_exists('delivery_ciphertext', $payload)) {
            unset($payload['delivery_ciphertext']);
            $payload['delivery_secret_destroyed_at'] = $this->clock->now()->format(DATE_ATOM);
        }
        return $payload;
    }
}
