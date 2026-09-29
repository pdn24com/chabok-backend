<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityEventRepositoryInterface;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityEventRecord;

final class EloquentOpportunityEventRepository implements OpportunityEventRepositoryInterface
{
    public function create(array $attributes): OpportunityEventRecord
    {
        return OpportunityEventRecord::query()->forceCreate($attributes);
    }

    public function listForOpportunity(string $hqId, string $opportunityId): Collection
    {
        return OpportunityEventRecord::query()
            ->where(['hq_id' => $hqId, 'opportunity_id' => $opportunityId])
            ->with(['actor' => fn ($actor) => $actor->select(['id', 'display_name'])])
            // Oldest first: the history reads as the story of the opportunity, not as a newsfeed.
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }
}
