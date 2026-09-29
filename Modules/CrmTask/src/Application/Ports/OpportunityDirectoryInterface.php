<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Ports;

/**
 * What a task needs to know about the opportunity it hangs off. The database refuses a task whose
 * customer disagrees with its opportunity, so the pair is proven here before the write is attempted.
 *
 * Implemented by Modules\CrmOpportunitie.
 */
interface OpportunityDirectoryInterface
{
    public function existsForCustomer(string $hqId, string $customerId, string $opportunityId): bool;

    /**
     * The titles of the given opportunities, keyed by ID, so a whole inbox page is named in one read.
     *
     * @param  list<string>  $opportunityIds
     * @return array<string, string|null>
     */
    public function titlesFor(string $hqId, array $opportunityIds): array;
}
