<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerExtendedDetails;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerExtendedDetailRecord;

/** The extended-details row of a customer. */
final readonly class GetCustomerExtendedDetailsResult
{
    public function __construct(
        public CustomerExtendedDetailRecord $details,
    ) {}
}
