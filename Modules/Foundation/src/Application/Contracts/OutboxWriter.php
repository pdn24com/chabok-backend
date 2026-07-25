<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface OutboxWriter
{
    /**
     * @param array<string, mixed> $payload
     */
    public function write(
        ?string $hqId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        string $correlationId,
        array $payload,
        int $eventVersion = 1,
        ?string $causationId = null,
    ): void;
}
