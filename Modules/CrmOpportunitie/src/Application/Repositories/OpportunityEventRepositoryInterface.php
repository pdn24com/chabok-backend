<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityEventRecord;

interface OpportunityEventRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OpportunityEventRecord;

    /**
     * The whole history of one opportunity, oldest move first, with the actor of each named.
     *
     * @return Collection<int, OpportunityEventRecord>
     */
    public function listForOpportunity(string $hqId, string $opportunityId): Collection;
}
