<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\ServiceCatalog\Application\Repositories\CatalogAuditRepositoryInterface;

final class EloquentCatalogAuditRepository implements CatalogAuditRepositoryInterface
{
    /** Every catalogue action key shares this prefix. */
    private const ACTION_PREFIX = 'SERVICE_CATALOG_%';

    public function paginateCatalogEvents(?string $hqId, ?string $targetId, int $page, int $pageSize): LengthAwarePaginator
    {
        return AuditEventRecord::query()->where('hq_id', $hqId)->where('action_key', 'like', self::ACTION_PREFIX)
            ->when($targetId !== null, fn ($query) => $query->where('target_id', $targetId))
            ->orderByDesc('created_at')->paginate($pageSize, page: $page);
    }
}
