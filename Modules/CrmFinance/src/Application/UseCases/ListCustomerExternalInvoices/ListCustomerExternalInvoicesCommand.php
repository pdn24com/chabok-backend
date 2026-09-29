<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerExternalInvoices;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerExternalInvoicesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
