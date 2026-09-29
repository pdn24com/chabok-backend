<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Dto;

final readonly class OutboxEventDto
{
    /** @param array<string, mixed> $payload Versioned external event content, validated by its registered schema. */
    public function __construct(
        public string $eventId,
        public ?string $hqId,
        public string $aggregateType,
        public string $aggregateId,
        public string $eventType,
        public int $eventVersion,
        public array $payload,
        public string $correlationId,
        public string $occurredAt,
    ) {}
}
