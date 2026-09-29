<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerDepartment;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;

/** The stored department, with the posts hanging off it. */
final readonly class CreateCustomerDepartmentResult
{
    public function __construct(
        public CustomerDepartmentRecord $department,
    ) {}
}
