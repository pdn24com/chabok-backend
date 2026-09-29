<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Contracts;

interface OutboxEventSchemaRegistryInterface
{
    /** @param array<string, mixed> $payload */
    public function assertValid(
        string $eventType,
        int $version,
        array $payload,
    ): void;
}
