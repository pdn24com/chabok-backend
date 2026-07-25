<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface AuditWriter
{
    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
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
