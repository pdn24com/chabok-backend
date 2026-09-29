<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerExtendedDetails;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCustomerExtendedDetailsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
