<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerAddress;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

/** The stored address-book entry. */
final readonly class CreateCustomerAddressResult
{
    public function __construct(
        public CustomerAddressRecord $address,
    ) {}
}
