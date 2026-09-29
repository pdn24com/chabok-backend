<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

/**
 * Port owned by Foundation, implemented by the Audit module.
 *
 * @see Modules/Audit/src/Infrastructure/Persistence/MySqlAuditWriter.php (bound in AuditServiceProvider)
 */
interface AuditWriterInterface
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function write(
        ?string $hqId,
        ?string $initiatorId,
        string $action,
        string $targetType,
        ?string $targetId,
        string $correlationId,
        ?array $before = null,
        ?array $after = null,
        ?string $safeNote = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sourceClient = null,
    ): void;
}
