<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

/**
 * Port owned by Foundation, implemented by the Outbox module.
 *
 * @see Modules/Outbox/src/Infrastructure/Persistence/MySqlOutboxWriter.php (bound in OutboxServiceProvider)
 */
interface OutboxWriterInterface
{
    /**
     * @param  array<string, mixed>  $payload
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
