<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Application\Dto\OpportunityBoardFiltersDto;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\OpportunityRecord;

interface OpportunityRepositoryInterface
{
    /**
     * Every opportunity of one customer whose current funnel step is still open, together with that
     * step, the nearest expected close first.
     *
     * @return Collection<int, OpportunityRecord>
     */
    public function openForCustomer(string $hqId, string $customerId): Collection;

    /** True when the tenant owns an opportunity under this ID against the named customer. */
    public function existsForCustomer(string $hqId, string $customerId, string $opportunityId): bool;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OpportunityRecord;

    /**
     * Every opportunity of one funnel that the board filters let through, with the customer, the owner
     * and the next open task each card prints. A board is drawn whole, so there is no page to ask for.
     *
     * @return Collection<int, OpportunityRecord>
     */
    public function listForBoard(string $hqId, OpportunityBoardFiltersDto $filters): Collection;

    public function findForTenant(string $hqId, string $opportunityId): ?OpportunityRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $opportunityId): ?OpportunityRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $opportunityId, array $attributes): void;

    public function existsForTenant(string $hqId, string $opportunityId): bool;

    /**
     * The titles of the given opportunities, keyed by ID, so a caller naming a page of rows reads them
     * in one query instead of one per row.
     *
     * @param  list<string>  $opportunityIds
     * @return array<string, string|null>
     */
    public function titlesFor(string $hqId, array $opportunityIds): array;
}
