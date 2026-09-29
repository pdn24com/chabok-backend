<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListOpportunityBoard;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\FunnelStepRecord;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\SalesFunnelRecord;

/**
 * One kanban board: the funnel it belongs to, its active steps in column order, and the opportunities
 * grouped under the step each one currently stands on. A step nobody has reached is still a column, so
 * the board keeps its shape whether or not anything is on it.
 */
final readonly class ListOpportunityBoardResult
{
    /**
     * @param  Collection<int, FunnelStepRecord>  $steps
     * @param  array<string, list<OpportunityRecord>>  $opportunitiesByStepId
     */
    public function __construct(
        public SalesFunnelRecord $funnel,
        public Collection $steps,
        public array $opportunitiesByStepId,
    ) {}
}
