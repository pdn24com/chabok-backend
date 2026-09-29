<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Application\Dto\OpportunityBoardFiltersDto;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmOpportunitie\Domain\Enums\FunnelStepOutcome;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;

final class EloquentOpportunityRepository implements OpportunityRepositoryInterface
{
    public function openForCustomer(string $hqId, string $customerId): Collection
    {
        return OpportunityRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId])
            ->whereRelation('currentStep', 'outcome_type', FunnelStepOutcome::OPEN->value)
            ->with(['currentStep' => fn ($step) => $step->select(['id', 'title', 'outcome_type'])])
            // An opportunity without an expected close date carries no deadline, so it sorts last.
            ->orderByRaw('expected_close is null')
            ->orderBy('expected_close')
            ->orderBy('id')
            ->get(['id', 'title', 'amount', 'current_step_id', 'expected_close']);
    }

    public function existsForCustomer(string $hqId, string $customerId, string $opportunityId): bool
    {
        return OpportunityRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId, 'opportunity_id' => $opportunityId])
            ->exists();
    }

    public function create(array $attributes): OpportunityRecord
    {
        return OpportunityRecord::query()->forceCreate($attributes);
    }

    public function listForBoard(string $hqId, OpportunityBoardFiltersDto $filters): Collection
    {
        return OpportunityRecord::query()
            ->where(['hq_id' => $hqId, 'funnel_id' => $filters->funnelId])
            ->when($filters->customerId !== null, fn ($query) => $query->where('customer_id', $filters->customerId))
            ->when($filters->assigneeId !== null, fn ($query) => $query->where('assignee_id', $filters->assigneeId))
            // Everything a card prints is read in the same pass, so a wide board costs one query per relation.
            ->with([
                'customer' => fn ($customer) => $customer->select(['id', 'display_name', 'phase']),
                'assignee' => fn ($assignee) => $assignee->select(['id', 'display_name']),
                'nextTask' => fn ($task) => $task->select(['id', 'opportunity_id', 'title', 'status', 'due_at']),
            ])
            // An opportunity without an expected close carries no deadline, so it sorts last in its column.
            ->orderByRaw('expected_close is null')
            ->orderBy('expected_close')
            ->orderBy('id')
            ->get();
    }

    public function findForTenant(string $hqId, string $opportunityId): ?OpportunityRecord
    {
        return OpportunityRecord::query()
            ->where(['hq_id' => $hqId, 'opportunity_id' => $opportunityId])
            ->with(['currentStep' => fn ($step) => $step->select(['id', 'code', 'title', 'sort_order', 'outcome_type'])])
            ->first();
    }

    public function lockForTenant(string $hqId, string $opportunityId): ?OpportunityRecord
    {
        return OpportunityRecord::query()
            ->where(['hq_id' => $hqId, 'opportunity_id' => $opportunityId])
            ->lockForUpdate()
            ->first();
    }

    public function update(string $hqId, string $opportunityId, array $attributes): void
    {
        OpportunityRecord::query()->where(['hq_id' => $hqId, 'opportunity_id' => $opportunityId])->update($attributes);
    }

    public function existsForTenant(string $hqId, string $opportunityId): bool
    {
        return OpportunityRecord::query()->where(['hq_id' => $hqId, 'opportunity_id' => $opportunityId])->exists();
    }

    public function titlesFor(string $hqId, array $opportunityIds): array
    {
        if ($opportunityIds === []) {
            return [];
        }

        return OpportunityRecord::query()
            ->where('hq_id', $hqId)
            ->whereIn('opportunity_id', $opportunityIds)
            ->pluck('title', 'id')
            ->mapWithKeys(fn (?string $title, int|string $id): array => [(string) $id => $title])
            ->all();
    }
}
