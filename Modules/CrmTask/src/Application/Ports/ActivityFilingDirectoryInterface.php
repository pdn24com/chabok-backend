<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Ports;

/**
 * What an interaction recorded on its own needs to know about the records it is filed against, beyond
 * what the customer and opportunity ports already answer: whose opportunity it is, and who may be named
 * as the person spoken to. The database refuses a contact that is not a PERSON, so it is proven here first.
 *
 * Implemented by CrmTask itself for now, reading the tenant's rows; a Customer/CrmOpportunitie adapter may replace it.
 */
interface ActivityFilingDirectoryInterface
{
    /** The customer that owns the opportunity, or null when the tenant has no such opportunity. */
    public function customerOfOpportunity(string $hqId, string $opportunityId): ?string;

    /** True when the tenant has a customer of this ID and it is a PERSON, not a company. */
    public function personExistsForTenant(string $hqId, string $customerId): bool;
}
