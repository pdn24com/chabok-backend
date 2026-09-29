<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerAddress;

use Modules\Customer\Application\Dto\CustomerAddressChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateCustomerAddressCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public string $addressId,
        public CustomerAddressChangesDto $changes,
    ) {}
}
