<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Ports;

/**
 * What a task needs to know about the customer it is filed against. Customer already reads CrmTask for
 * the open work on its detail page, so the dependency is inverted here rather than turned into a cycle.
 *
 * Implemented by Modules\Customer.
 */
interface CustomerDirectoryInterface
{
    public function existsForTenant(string $hqId, string $customerId): bool;

    /**
     * The display names of the given customers, keyed by ID, so a whole inbox page is named in one read.
     *
     * @param  list<string>  $customerIds
     * @return array<string, string|null>
     */
    public function displayNamesFor(string $hqId, array $customerIds): array;
}
