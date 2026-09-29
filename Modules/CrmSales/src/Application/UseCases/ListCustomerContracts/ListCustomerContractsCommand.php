<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\ListCustomerContracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerContractsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
