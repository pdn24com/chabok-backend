<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Mappers;

use Modules\Foundation\Application\Dto\OutboxEventDto;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

final class OutboxEventMapper
{
    public static function fromRecord(OutboxEventRecord $event): OutboxEventDto
    {
        return new OutboxEventDto(
            eventId: $event->event_id,
            hqId: $event->hq_id,
            aggregateType: $event->aggregate_type,
            aggregateId: $event->aggregate_id,
            eventType: $event->event_type,
            eventVersion: $event->event_version,
            payload: $event->payload,
            correlationId: $event->correlation_id,
            occurredAt: $event->occurred_at,
        );
    }
}
