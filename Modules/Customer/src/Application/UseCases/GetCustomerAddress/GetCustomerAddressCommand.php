<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerAddress;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCustomerAddressCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public string $addressId,
    ) {}
}
