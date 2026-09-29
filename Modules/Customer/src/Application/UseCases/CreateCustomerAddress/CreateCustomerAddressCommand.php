<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerAddress;

use Modules\Customer\Application\Dto\CustomerAddressDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerAddressCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public CustomerAddressDraftDto $input,
    ) {}
}
