<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\CrmTask\Application\Ports\ActivityFilingDirectoryInterface;

/**
 * Answers the filing questions with two narrow tenant-scoped reads, because Customer and CrmOpportunitie
 * do not yet expose them through a CrmTask port. Rebind the port to an adapter in those modules to move
 * the reads to their owners.
 */
final class DatabaseActivityFilingDirectory implements ActivityFilingDirectoryInterface
{
    public function customerOfOpportunity(string $hqId, string $opportunityId): ?string
    {
        $customerId = DB::table('crm_opportunities')->where(['hq_id' => $hqId, 'id' => $opportunityId])->value('customer_id');

        return $customerId === null ? null : (string) $customerId;
    }

    public function personExistsForTenant(string $hqId, string $customerId): bool
    {
        return DB::table('crm_customers')->where(['hq_id' => $hqId, 'id' => $customerId, 'kind' => 'PERSON'])->exists();
    }
}
