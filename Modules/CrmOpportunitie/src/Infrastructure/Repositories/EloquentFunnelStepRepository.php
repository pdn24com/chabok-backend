<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Application\Repositories\FunnelStepRepositoryInterface;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\FunnelStepRecord;

final class EloquentFunnelStepRepository implements FunnelStepRepositoryInterface
{
    public function activeForFunnel(string $hqId, string $funnelId): Collection
    {
        return $this->activeStepsOf($hqId, $funnelId)->orderBy('sort_order')->orderBy('id')->get();
    }

    public function firstForFunnel(string $hqId, string $funnelId): ?FunnelStepRecord
    {
        return $this->activeStepsOf($hqId, $funnelId)->orderBy('sort_order')->orderBy('id')->first();
    }

    public function findInFunnel(string $hqId, string $funnelId, string $stepId): ?FunnelStepRecord
    {
        return FunnelStepRecord::query()
            ->where(['hq_id' => $hqId, 'funnel_id' => $funnelId, 'sales_funnel_step_id' => $stepId])
            ->first();
    }

    /** @return Builder<FunnelStepRecord> */
    private function activeStepsOf(string $hqId, string $funnelId): Builder
    {
        return FunnelStepRecord::query()->where(['hq_id' => $hqId, 'funnel_id' => $funnelId, 'is_active' => true]);
    }
}
