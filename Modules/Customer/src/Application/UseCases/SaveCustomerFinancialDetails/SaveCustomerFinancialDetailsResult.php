<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerFinancialDetails;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerFinancialDetailRecord;

/** The financial-summary row as the save left it. */
final readonly class SaveCustomerFinancialDetailsResult
{
    public function __construct(
        public CustomerFinancialDetailRecord $details,
    ) {}
}
