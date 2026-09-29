<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Persistence;

use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Support\SensitiveDataRedactor;

final class MySqlAuditWriter implements AuditWriterInterface
{
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
    ): void {
        AuditEventRecord::query()->forceCreate([

            'hq_id' => $hqId,
            'initiator_id' => $initiatorId,
            'action_key' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_snapshot' => $before === null ? null : SensitiveDataRedactor::context($before),
            'after_snapshot' => $after === null ? null : SensitiveDataRedactor::context($after),
            'safe_note' => $safeNote === null ? null : SensitiveDataRedactor::message($safeNote),
            'ip_address_hash' => $ipAddress === null ? null : hash('sha256', $ipAddress),
            'user_agent_hash' => $userAgent === null ? null : hash('sha256', $userAgent),
            'source_client' => $sourceClient,
            'correlation_id' => $correlationId,
        ]);
    }
}
