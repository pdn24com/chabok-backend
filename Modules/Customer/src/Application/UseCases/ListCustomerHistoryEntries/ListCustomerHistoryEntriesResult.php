<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerHistoryEntries;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Customer\Application\Dto\CustomerHistoryEntryDto;

/** One page of history rows, from a single category or from the merged timeline. */
final readonly class ListCustomerHistoryEntriesResult
{
    /**
     * @param  LengthAwarePaginator<CustomerHistoryEntryDto>  $entries
     */
    public function __construct(
        public LengthAwarePaginator $entries,
    ) {}
}
