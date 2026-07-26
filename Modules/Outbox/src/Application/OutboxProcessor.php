<?php

declare(strict_types=1);

namespace Modules\Outbox\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\OutboxEventPublisher;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorder;
use Modules\Foundation\Application\OutboxPublishException;

final readonly class OutboxProcessor
{
    public function __construct(
        private OutboxEventPublisher $publisher,
        private AuditWriter $audit,
        private SecurityMetricRecorder $metrics,
    ) {}

    /** @return array{claimed: int, published: int, failed: int, dead_lettered: int} */
    public function runOnce(int $limit = 25, ?string $workerId = null): array
    {
        $workerId ??= gethostname().':'.getmypid();
        $events = $this->claim(max(1, min($limit, 250)), $workerId);
        $result = ['claimed' => $events->count(), 'published' => 0, 'failed' => 0, 'dead_lettered' => 0];
        Redis::connection('cache')->setex(
            'chabok:outbox:heartbeat',
            (int) config('chabok.outbox.heartbeat_ttl_seconds', 180),
            now()->toIso8601String(),
        );

        foreach ($events as $event) {
            Log::shareContext([
                'correlation_id' => (string) $event->correlation_id,
                'event_id' => (string) $event->event_id,
                'event_type' => (string) $event->event_type,
            ]);
            try {
                $receipt = $this->publisher->publish($this->eventPayload($event));
                $this->complete((string) $event->event_id, (string) $event->claim_token, $receipt);
                $result['published']++;
            } catch (OutboxPublishException $exception) {
                $deadLettered = $this->fail($event, $exception->failureCode, $exception->retryable);
                $result[$deadLettered ? 'dead_lettered' : 'failed']++;
            } catch (\Throwable $exception) {
                report($exception);
                $deadLettered = $this->fail($event, 'PUBLISHER_ERROR', true);
                $result[$deadLettered ? 'dead_lettered' : 'failed']++;
            } finally {
                Log::flushSharedContext();
            }
        }

        return $result;
    }

    public function replay(string $eventId): void
    {
        DB::transaction(function () use ($eventId): void {
            $event = DB::table('outbox_events')->where('event_id', $eventId)->lockForUpdate()->first();
            if ($event === null) {
                throw new \InvalidArgumentException('Outbox event not found.');
            }
            if (! in_array($event->publication_state, ['FAILED', 'DEAD_LETTER'], true)) {
                throw new \LogicException('Only failed or dead-lettered events can be replayed.');
            }
            DB::table('outbox_events')->where('event_id', $eventId)->update([
                'publication_state' => 'PENDING',
                'attempts' => 0,
                'next_attempt_at' => null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
                'last_failure_code' => null,
                'dead_lettered_at' => null,
            ]);
        });
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function claim(int $limit, string $workerId): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($limit, $workerId) {
            $staleBefore = now()->subSeconds((int) config('chabok.outbox.claim_timeout_seconds', 120));
            $events = DB::table('outbox_events')
                ->whereIn('publication_state', ['PENDING', 'FAILED'])
                ->where(function ($query): void {
                    $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                })
                ->where(function ($query) use ($staleBefore): void {
                    $query->whereNull('claim_token')->orWhere('claimed_at', '<=', $staleBefore);
                })
                ->where('attempts', '<', (int) config('chabok.outbox.max_attempts', 5))
                ->orderBy('occurred_at')
                ->limit($limit)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            foreach ($events as $event) {
                $claimToken = (string) Str::uuid();
                DB::table('outbox_events')->where('event_id', $event->event_id)->update([
                    'claim_token' => $claimToken,
                    'claimed_by' => mb_substr($workerId, 0, 120),
                    'claimed_at' => now(),
                    'attempts' => (int) $event->attempts + 1,
                    'last_attempt_at' => now(),
                ]);
                $event->claim_token = $claimToken;
                $event->attempts = (int) $event->attempts + 1;
            }

            return $events;
        });
    }

    /** @param array{provider: string, receipt: string} $receipt */
    private function complete(string $eventId, string $claimToken, array $receipt): void
    {
        DB::transaction(function () use ($eventId, $claimToken, $receipt): void {
            $event = DB::table('outbox_events')->where([
                'event_id' => $eventId,
                'claim_token' => $claimToken,
            ])->lockForUpdate()->first();
            if ($event === null) {
                throw new \LogicException('Outbox claim was lost before completion.');
            }
            $payload = json_decode((string) $event->payload, true, 512, JSON_THROW_ON_ERROR);
            $payload = $this->destroyDeliverySecret($payload);
            $payload['publication_receipt'] = [
                'provider' => $receipt['provider'],
                'receipt' => $receipt['receipt'],
            ];
            DB::table('outbox_events')->where('event_id', $eventId)->update([
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'publication_state' => 'PUBLISHED',
                'published_at' => now(),
                'next_attempt_at' => null,
                'last_failure_code' => null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
            ]);
        });
    }

    private function fail(object $event, string $failureCode, bool $retryable): bool
    {
        $maxAttempts = (int) config('chabok.outbox.max_attempts', 5);
        $deadLettered = ! $retryable || (int) $event->attempts >= $maxAttempts;
        DB::transaction(function () use ($event, $failureCode, $deadLettered): void {
            $current = DB::table('outbox_events')->where([
                'event_id' => $event->event_id,
                'claim_token' => $event->claim_token,
            ])->lockForUpdate()->first();
            if ($current === null) {
                return;
            }
            $delay = min(
                (int) config('chabok.outbox.max_backoff_seconds', 900),
                (int) config('chabok.outbox.base_backoff_seconds', 5) * (2 ** max(0, (int) $current->attempts - 1)),
            );
            DB::table('outbox_events')->where('event_id', $event->event_id)->update([
                'publication_state' => $deadLettered ? 'DEAD_LETTER' : 'FAILED',
                'next_attempt_at' => $deadLettered ? null : now()->addSeconds($delay),
                'last_failure_code' => mb_substr($failureCode, 0, 120),
                'dead_lettered_at' => $deadLettered ? now() : null,
                'claim_token' => null,
                'claimed_by' => null,
                'claimed_at' => null,
            ]);
            if ($deadLettered) {
                $this->audit->write(
                    $current->hq_id === null ? null : (string) $current->hq_id,
                    null,
                    'OUTBOX_EVENT_DEAD_LETTERED',
                    'OUTBOX_EVENT',
                    (string) $current->event_id,
                    (string) $current->correlation_id,
                    safeNote: "Failure code: {$failureCode}",
                    sourceClient: 'OUTBOX_WORKER',
                );
            }
        });
        $this->metrics->increment(
            $deadLettered ? 'outbox.dead_lettered' : 'outbox.retry_scheduled',
            ['code' => preg_replace('/[^A-Z0-9_.-]/', '_', strtoupper($failureCode)) ?: 'UNKNOWN'],
        );

        return $deadLettered;
    }

    /** @return array<string, mixed> */
    private function eventPayload(object $event): array
    {
        return [
            'event_id' => (string) $event->event_id,
            'hq_id' => $event->hq_id === null ? null : (string) $event->hq_id,
            'aggregate_type' => (string) $event->aggregate_type,
            'aggregate_id' => (string) $event->aggregate_id,
            'event_type' => (string) $event->event_type,
            'event_version' => (int) $event->event_version,
            'payload' => json_decode((string) $event->payload, true, 512, JSON_THROW_ON_ERROR),
            'correlation_id' => (string) $event->correlation_id,
            'occurred_at' => (string) $event->occurred_at,
        ];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function destroyDeliverySecret(array $payload): array
    {
        if (array_key_exists('delivery_ciphertext', $payload)) {
            unset($payload['delivery_ciphertext']);
            $payload['delivery_secret_destroyed_at'] = now()->toIso8601String();
        }

        return $payload;
    }
}
