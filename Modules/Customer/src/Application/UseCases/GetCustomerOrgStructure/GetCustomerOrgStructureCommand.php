<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerOrgStructure;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCustomerOrgStructureCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
