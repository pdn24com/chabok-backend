<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerAddress;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

/** The address-book entry as the change left it. */
final readonly class UpdateCustomerAddressResult
{
    public function __construct(
        public CustomerAddressRecord $address,
    ) {}
}
