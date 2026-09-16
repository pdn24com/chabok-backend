<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Persistence;

use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\SensitiveDataRedactor;
use Modules\Outbox\Application\OutboxEventSchemaRegistry;

final readonly class MySqlOutboxWriter implements OutboxWriter
{
    public function __construct(private OutboxEventSchemaRegistry $schemas)
    {
    }

    public function write(
        ?string $hqId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        string $correlationId,
        array $payload,
        int $eventVersion = 1,
        ?string $causationId = null,
    ): void
    {
        $this->schemas->assertValid($eventType, $eventVersion, $payload);
        OutboxEventRecord::query()->insert([
            'event_id' => (string) Str::uuid(),
            'hq_id' => $hqId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'event_version' => $eventVersion,
            'payload' => json_encode(SensitiveDataRedactor::context($payload), JSON_THROW_ON_ERROR),
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'occurred_at' => now(),
            'publication_state' => 'PENDING',
            'attempts' => 0,
            'next_attempt_at' => null,
            'published_at' => null,
            'last_failure_code' => null,
            'created_at' => now(),
        ]);
    }
}
