<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerAddresses;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerAddressesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
