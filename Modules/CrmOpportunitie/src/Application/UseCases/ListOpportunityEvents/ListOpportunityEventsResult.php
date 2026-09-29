<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListOpportunityEvents;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityEventRecord;

/** The whole history of one opportunity, oldest move first. */
final readonly class ListOpportunityEventsResult
{
    /**
     * @param  Collection<int, OpportunityEventRecord>  $events
     */
    public function __construct(
        public Collection $events,
    ) {}
}
