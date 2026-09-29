<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerProfile;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** The profile field set as the change left it. */
final readonly class UpdateCustomerProfileResult
{
    public function __construct(
        public CustomerRecord $customer,
    ) {}
}
