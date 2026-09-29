<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomer;

use Modules\Customer\Application\Dto\CustomerDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public CustomerDraftDto $input,
    ) {}
}
