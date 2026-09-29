<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomers;

use Modules\Customer\Application\Dto\CustomerListFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomersCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public CustomerListFiltersDto $filters = new CustomerListFiltersDto,
    ) {}
}
