<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\PublishPendingEvents;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Exceptions\OutboxPublishException;
use Modules\Foundation\Application\Ports\OutboxEventPublisherInterface;
use Modules\Outbox\Application\Contracts\OutboxClaimServiceInterface;
use Modules\Outbox\Application\Contracts\OutboxSettingsInterface;
use Modules\Outbox\Application\Contracts\PublicationRecorderInterface;
use Modules\Outbox\Application\Contracts\WorkerRuntimeInterface;
use Modules\Outbox\Application\Mappers\OutboxEventMapper;
use Throwable;

final readonly class PublishPendingEventsHandler
{
    public function __construct(
        private WorkerRuntimeInterface $workerRuntime,
        private OutboxClaimServiceInterface $outboxClaimService,
        private OutboxSettingsInterface $outboxSettings,
        private ClockInterface $clock,
        private OutboxEventPublisherInterface $outboxEventPublisher,
        private PublicationRecorderInterface $publicationRecorder,
    ) {}

    public function handle(PublishPendingEventsCommand $command): PublishPendingEventsResult
    {
        $limit = $command->limit;
        $workerId = $command->workerId;
        $workerId ??= $this->workerRuntime->workerId();
        $events = $this->outboxClaimService->claim(max(1, min($limit, 250)), $workerId);
        $published = 0;
        $failed = 0;
        $deadLetteredCount = 0;
        $this->workerRuntime->heartbeat($this->outboxSettings->heartbeatTtl(), $this->clock->now()->format(DATE_ATOM));
        foreach ($events as $event) {
            $this->workerRuntime->shareContext([
                'correlation_id' => (string) $event->correlation_id,
                'event_id' => (string) $event->event_id,
                'event_type' => (string) $event->event_type,
            ]);
            try {
                $receipt = $this->outboxEventPublisher->publish(OutboxEventMapper::fromRecord($event));
                $this->publicationRecorder->complete((string) $event->event_id, (string) $event->claim_token, $receipt);
                $published++;
            } catch (OutboxPublishException $exception) {
                $deadLettered = $this->publicationRecorder->fail($event, $exception->failureCode, $exception->retryable);
                if ($deadLettered) {
                    $deadLetteredCount++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $this->workerRuntime->report($exception);
                $deadLettered = $this->publicationRecorder->fail($event, 'PUBLISHER_ERROR', true);
                if ($deadLettered) {
                    $deadLetteredCount++;
                } else {
                    $failed++;
                }
            } finally {
                $this->workerRuntime->clearContext();
            }
        }

        return new PublishPendingEventsResult(count($events), $published, $failed, $deadLetteredCount);
    }
}
