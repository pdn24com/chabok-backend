<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerFinancialDetails;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerFinancialDetailRecord;

/** The financial-summary row of a customer. */
final readonly class GetCustomerFinancialDetailsResult
{
    public function __construct(
        public CustomerFinancialDetailRecord $details,
    ) {}
}
