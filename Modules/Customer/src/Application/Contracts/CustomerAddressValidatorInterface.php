<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Customer\Application\Dto\CustomerAddressDraftDto;
use Modules\Customer\Application\Dto\CustomerAddressDto;

interface CustomerAddressValidatorInterface
{
    /** The location captured while a customer is created, where a reference city is demanded for Iran. */
    public function validate(CustomerAddressDto $address): void;

    /** One address-book entry, where the reference province and city are optional even for Iran. */
    public function validateEntry(CustomerAddressDraftDto $address): void;
}
