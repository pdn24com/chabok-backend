<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerDepartment;

use Modules\Customer\Application\Dto\CustomerDepartmentChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateCustomerDepartmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public string $departmentId,
        public CustomerDepartmentChangesDto $changes,
    ) {}
}
