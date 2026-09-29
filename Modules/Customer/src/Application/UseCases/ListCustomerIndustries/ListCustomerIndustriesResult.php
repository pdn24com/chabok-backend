<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerIndustries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerIndustryRecord;

/** Every industry of one customer, the primary one first. */
final readonly class ListCustomerIndustriesResult
{
    /**
     * @param  Collection<int, CustomerIndustryRecord>  $industries
     */
    public function __construct(
        public Collection $industries,
    ) {}
}
