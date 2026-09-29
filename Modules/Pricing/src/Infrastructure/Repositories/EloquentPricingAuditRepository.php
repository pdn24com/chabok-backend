<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Pricing\Application\Repositories\PricingAuditRepositoryInterface;

final class EloquentPricingAuditRepository implements PricingAuditRepositoryInterface
{
    /** Every pricing action key shares this prefix. */
    private const ACTION_PREFIX = 'PRICING_%';

    public function paginatePricingEvents(?string $hqId, ?string $targetId, int $page, int $pageSize): LengthAwarePaginator
    {
        return AuditEventRecord::query()->where('hq_id', $hqId)->where('action_key', 'like', self::ACTION_PREFIX)
            ->when($targetId !== null, fn ($query) => $query->where('target_id', $targetId))
            ->orderByDesc('created_at')->paginate($pageSize, page: $page);
    }
}
