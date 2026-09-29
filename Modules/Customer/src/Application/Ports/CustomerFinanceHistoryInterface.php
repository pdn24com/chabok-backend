<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Ports;

use Modules\Customer\Application\Dto\CustomerHistoryEntryDto;

/**
 * The finance side of the customer history. CrmFinance already reads Customer to prove the customer of an
 * invoice, so the dependency is inverted here rather than pointed back and turned into a cycle.
 *
 * Implemented by Modules\CrmFinance.
 */
interface CustomerFinanceHistoryInterface
{
    /** @return list<CustomerHistoryEntryDto> */
    public function historyForCustomer(string $hqId, string $customerId): array;
}
