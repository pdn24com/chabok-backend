<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\PublishPendingEvents;

use Modules\Foundation\Application\OutboxPublishException;

final readonly class PublishPendingEventsHandler
{
    public function __construct(
        private \Modules\Outbox\Application\Contracts\WorkerRuntime $runtime,
        private \Modules\Outbox\Application\Services\OutboxClaimService $outboxClaimService,
        private \Modules\Outbox\Application\Contracts\OutboxSettings $settings,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\OutboxEventPublisher $publisher,
        private \Modules\Outbox\Application\Services\OutboxPayload $outboxPayload,
        private \Modules\Outbox\Application\Services\PublicationRecorder $publicationRecorder,
    )
    {
    }

    public function handle(PublishPendingEventsCommand $command): PublishPendingEventsResult
    {
        return new PublishPendingEventsResult($this->execute($command->limit, $command->workerId));
    }

    private function execute(int $limit = 25, ?string $workerId = null): array
    {
        $workerId ??= $this->runtime->workerId();
        $events = $this->outboxClaimService->claim(max(1, min($limit, 250)), $workerId);
        $result = ['claimed' => count($events), 'published' => 0, 'failed' => 0, 'dead_lettered' => 0];
        $this->runtime->heartbeat($this->settings->heartbeatTtl(), $this->clock->now()->format(DATE_ATOM));
        foreach ($events as $event) {
            $this->runtime->shareContext([
                'correlation_id' => (string) $event->correlation_id,
                'event_id' => (string) $event->event_id,
                'event_type' => (string) $event->event_type,
            ]);
            try {
                $receipt = $this->publisher->publish($this->outboxPayload->eventPayload($event));
                $this->publicationRecorder->complete((string) $event->event_id, (string) $event->claim_token, $receipt);
                $result['published']++;
            } catch (OutboxPublishException $exception) {
                $deadLettered = $this->publicationRecorder->fail($event, $exception->failureCode, $exception->retryable);
                $result[$deadLettered ? 'dead_lettered' : 'failed']++;
            } catch (\Throwable $exception) {
                $this->runtime->report($exception);
                $deadLettered = $this->publicationRecorder->fail($event, 'PUBLISHER_ERROR', true);
                $result[$deadLettered ? 'dead_lettered' : 'failed']++;
            } finally {
                $this->runtime->clearContext();
            }
        }
        return $result;
    }
}
