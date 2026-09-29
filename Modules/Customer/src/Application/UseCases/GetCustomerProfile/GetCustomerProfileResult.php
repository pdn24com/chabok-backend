<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerProfile;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** The editable profile field set of a customer. */
final readonly class GetCustomerProfileResult
{
    public function __construct(
        public CustomerRecord $customer,
    ) {}
}
