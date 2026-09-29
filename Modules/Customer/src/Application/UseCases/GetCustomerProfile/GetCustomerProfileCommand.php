<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerProfile;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCustomerProfileCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
