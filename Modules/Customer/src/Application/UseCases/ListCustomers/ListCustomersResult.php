<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomers;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** One page of the customer list. */
final readonly class ListCustomersResult
{
    /**
     * @param  LengthAwarePaginator<CustomerRecord>  $customers
     */
    public function __construct(
        public LengthAwarePaginator $customers,
    ) {}
}
