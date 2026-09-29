<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerAddresses;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

/** The whole address book of one customer, default entry first. */
final readonly class ListCustomerAddressesResult
{
    /**
     * @param  Collection<int, CustomerAddressRecord>  $addresses
     */
    public function __construct(
        public Collection $addresses,
    ) {}
}
