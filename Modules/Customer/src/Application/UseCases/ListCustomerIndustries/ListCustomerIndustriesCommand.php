<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerIndustries;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerIndustriesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
