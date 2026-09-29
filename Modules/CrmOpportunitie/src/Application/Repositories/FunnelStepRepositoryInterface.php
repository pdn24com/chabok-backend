<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\FunnelStepRecord;

interface FunnelStepRepositoryInterface
{
    /**
     * The active steps of one funnel in board order, which is the order of the kanban columns.
     *
     * @return Collection<int, FunnelStepRecord>
     */
    public function activeForFunnel(string $hqId, string $funnelId): Collection;

    /** The step a new opportunity starts on: the active step of lowest sort order in the funnel. */
    public function firstForFunnel(string $hqId, string $funnelId): ?FunnelStepRecord;

    /** One step, only when it belongs to the named funnel of the tenant. */
    public function findInFunnel(string $hqId, string $funnelId, string $stepId): ?FunnelStepRecord;
}
