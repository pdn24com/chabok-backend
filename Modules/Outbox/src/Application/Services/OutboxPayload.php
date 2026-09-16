<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Services;

final readonly class OutboxPayload
{
    public function eventPayload(object $event): array
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
}
