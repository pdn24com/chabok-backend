<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** One customer together with the open work the detail view shows next to it. */
final readonly class CustomerDetailDto
{
    /**
     * @param  Collection<int, TaskRecord>  $openTasks
     * @param  Collection<int, OpportunityRecord>  $openOpportunities
     */
    public function __construct(
        public CustomerRecord $customer,
        public Collection $openTasks,
        public Collection $openOpportunities,
    ) {}
}
