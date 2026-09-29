<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomer;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** The customer as the create use case leaves it, with the address, mobile and industry it just wrote. */
final readonly class CreateCustomerResult
{
    public function __construct(
        public CustomerRecord $customer,
    ) {}
}
