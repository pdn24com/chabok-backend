<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Services;

use Illuminate\Database\ConnectionInterface;
use LogicException;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorderInterface;
use Modules\Foundation\Application\Dto\PublicationReceiptDto;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Outbox\Application\Contracts\OutboxSettingsInterface;
use Modules\Outbox\Application\Contracts\PublicationRecorderInterface;
use Modules\Outbox\Application\Repositories\OutboxEventRepositoryInterface;
use Modules\Outbox\Domain\Enums\PublicationState;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

final readonly class PublicationRecorder implements PublicationRecorderInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private OutboxSettingsInterface $outboxSettings,
        private AuditWriterInterface $auditWriter,
        private SecurityMetricRecorderInterface $securityMetricRecorder,
        private OutboxEventRepositoryInterface $outboxEventRepository,
    ) {}

    public function complete(
        string $eventId,
        string $claimToken,
        PublicationReceiptDto $receipt,
    ): void {
        $this->connection->transaction(function () use ($eventId, $claimToken, $receipt): void {
            $event = $this->outboxEventRepository->lockByClaim($eventId, $claimToken);
            if ($event === null) {
                throw new LogicException('Outbox claim was lost before completion.');
            }
            $payload = $this->destroyDeliverySecret($event->payload);
            $payload['publication_receipt'] = ['provider' => $receipt->provider, 'receipt' => $receipt->receipt];
            $this->outboxEventRepository->apply($event, [
                'payload' => $payload,
                'publication_state' => PublicationState::PUBLISHED,
                'published_at' => $this->clock->now(),
                'next_attempt_at' => null,
                'last_failure_code' => null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
            ]);
        }, attempts: 1);
    }

    public function fail(
        OutboxEventRecord $event,
        string $failureCode,
        bool $retryable,
    ): bool {
        $maxAttempts = $this->outboxSettings->maxAttempts();
        $deadLettered = ! $retryable || (int) $event->attempts >= $maxAttempts;
        $this->connection->transaction(function () use ($event, $failureCode, $deadLettered): void {
            $current = $this->outboxEventRepository->lockByClaim((string) $event->event_id, $event->claim_token);
            if ($current === null) {
                return;
            }
            $delay = min($this->outboxSettings->maxBackoff(), $this->outboxSettings->baseBackoff() * 2 ** max(0, (int) $current->attempts - 1));
            $this->outboxEventRepository->apply($current, [
                'publication_state' => $deadLettered ? PublicationState::DEAD_LETTER : PublicationState::FAILED,
                'next_attempt_at' => $deadLettered ? null : $this->clock->now()->modify('+'.$delay.' seconds'),
                'last_failure_code' => mb_substr($failureCode, 0, 120),
                'dead_lettered_at' => $deadLettered ? $this->clock->now() : null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
            ]);
            if ($deadLettered) {
                $this->auditWriter->write($current->hq_id === null ? null : (string) $current->hq_id, null, 'OUTBOX_EVENT_DEAD_LETTERED', 'OUTBOX_EVENT', (string) $current->event_id, (string) $current->correlation_id, safeNote: "Failure code: {$failureCode}", sourceClient: 'OUTBOX_WORKER');
            }
        }, attempts: 1);
        $this->securityMetricRecorder->increment($deadLettered ? 'outbox.dead_lettered' : 'outbox.retry_scheduled', ['code' => preg_replace('/[^A-Z0-9_.-]/', '_', strtoupper($failureCode)) ?: 'UNKNOWN']);

        return $deadLettered;
    }

    private function destroyDeliverySecret(array $payload): array
    {
        if (array_key_exists('delivery_ciphertext', $payload)) {
            unset($payload['delivery_ciphertext']);
            $payload['delivery_secret_destroyed_at'] = $this->clock->now()->format(DATE_ATOM);
        }

        return $payload;
    }
}
