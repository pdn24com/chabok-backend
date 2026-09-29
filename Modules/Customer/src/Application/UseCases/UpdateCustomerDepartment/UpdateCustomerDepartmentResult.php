<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerDepartment;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;

/** The department as the change left it. */
final readonly class UpdateCustomerDepartmentResult
{
    public function __construct(
        public CustomerDepartmentRecord $department,
    ) {}
}
