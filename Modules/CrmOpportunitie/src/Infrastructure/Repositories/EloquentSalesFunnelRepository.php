<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Application\Repositories\SalesFunnelRepositoryInterface;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\SalesFunnelRecord;

final class EloquentSalesFunnelRepository implements SalesFunnelRepositoryInterface
{
    public function listForTenant(string $hqId, ?bool $active = null): Collection
    {
        return SalesFunnelRecord::query()
            ->where('hq_id', $hqId)
            ->when($active !== null, fn ($query) => $query->where('is_active', $active))
            // A retired step stays on the funnel it belongs to, but it is never a board column.
            ->with(['steps' => fn ($steps) => $steps->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->orderBy('title')
            ->orderBy('id')
            ->get();
    }

    public function findForTenant(string $hqId, string $funnelId): ?SalesFunnelRecord
    {
        return SalesFunnelRecord::query()->where(['hq_id' => $hqId, 'sales_funnel_id' => $funnelId])->first();
    }
}
