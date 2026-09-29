<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Audit\Application\Repositories\AuditEventRepositoryInterface;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;

final class EloquentAuditEventRepository implements AuditEventRepositoryInterface
{
    public function historyForResource(string $hqId, string $resourceType, string $resourceId): Collection
    {
        return AuditEventRecord::query()
            ->where(['hq_id' => $hqId, 'target_type' => $resourceType, 'target_id' => $resourceId])
            // The initiator name belongs to every row, so it is read once instead of per row.
            ->with(['initiator' => fn ($initiator) => $initiator->select(['id', 'display_name'])])
            ->orderByDesc('id')
            ->get(['id', 'action_key', 'safe_note', 'initiator_id', 'created_at']);
    }
}
