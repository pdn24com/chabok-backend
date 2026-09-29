<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Persistence;

use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Application\Support\SensitiveDataRedactor;
use Modules\Outbox\Application\Contracts\OutboxEventSchemaRegistryInterface;
use Modules\Outbox\Domain\Enums\PublicationState;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

final readonly class MySqlOutboxWriter implements OutboxWriterInterface
{
    public function __construct(private OutboxEventSchemaRegistryInterface $outboxEventSchemaRegistry) {}

    public function write(
        ?string $hqId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        string $correlationId,
        array $payload,
        int $eventVersion = 1,
        ?string $causationId = null,
    ): void {
        $this->outboxEventSchemaRegistry->assertValid($eventType, $eventVersion, $payload);
        OutboxEventRecord::query()->forceCreate([

            'hq_id' => $hqId,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'event_version' => $eventVersion,
            'payload' => SensitiveDataRedactor::context($payload),
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'occurred_at' => now(),
            'publication_state' => PublicationState::PENDING,
            'attempts' => 0,
            'next_attempt_at' => null,
            'published_at' => null,
            'last_failure_code' => null,
        ]);
    }
}
