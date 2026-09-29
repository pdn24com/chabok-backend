<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerIndustries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerIndustryRecord;

/** The industries of the customer as the replacement left them, the primary one first. */
final readonly class SaveCustomerIndustriesResult
{
    /**
     * @param  Collection<int, CustomerIndustryRecord>  $industries
     */
    public function __construct(
        public Collection $industries,
    ) {}
}
