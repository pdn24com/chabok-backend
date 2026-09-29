<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerFinancialDetails;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCustomerFinancialDetailsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
