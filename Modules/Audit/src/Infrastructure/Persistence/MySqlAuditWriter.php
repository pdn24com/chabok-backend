<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Persistence;

use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\SensitiveDataRedactor;

final class MySqlAuditWriter implements AuditWriter
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
    ): void
    {
        AuditEventRecord::query()->toBase()->insert([
            'audit_id' => (string) Str::uuid(),
            'hq_id' => $hqId,
            'initiator_id' => $initiatorId,
            'action_key' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_snapshot' => $before === null ? null : json_encode(SensitiveDataRedactor::context($before), JSON_THROW_ON_ERROR),
            'after_snapshot' => $after === null ? null : json_encode(SensitiveDataRedactor::context($after), JSON_THROW_ON_ERROR),
            'safe_note' => $safeNote === null ? null : SensitiveDataRedactor::message($safeNote),
            'ip_address_hash' => $ipAddress === null ? null : hash('sha256', $ipAddress),
            'user_agent_hash' => $userAgent === null ? null : hash('sha256', $userAgent),
            'source_client' => $sourceClient,
            'correlation_id' => $correlationId,
            'created_at' => now(),
        ]);
    }
}
