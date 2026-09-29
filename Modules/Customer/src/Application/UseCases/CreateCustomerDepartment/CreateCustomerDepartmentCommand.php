<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerDepartment;

use Modules\Customer\Application\Dto\CustomerDepartmentDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerDepartmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public CustomerDepartmentDraftDto $input,
    ) {}
}
