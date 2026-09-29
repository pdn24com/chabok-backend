<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerAddress;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

/** One address-book entry. */
final readonly class GetCustomerAddressResult
{
    public function __construct(
        public CustomerAddressRecord $address,
    ) {}
}
