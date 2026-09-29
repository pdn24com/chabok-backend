<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerDepartment;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerOrgStructureValidatorInterface;
use Modules\Customer\Application\Repositories\CustomerDepartmentRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class UpdateCustomerDepartmentHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerDepartmentRepositoryInterface $customerDepartmentRepository,
        private CustomerOrgStructureValidatorInterface $customerOrgStructureValidator,
    ) {}

    public function handle(UpdateCustomerDepartmentCommand $command): UpdateCustomerDepartmentResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $changes = $command->changes;
        if ($changes->touchesNothing()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['*' => ['customer.department_change_is_empty']]);
        }

        $department = $this->connection->transaction(function () use ($command, $hqId, $changes): CustomerDepartmentRecord {
            // Locked for the whole transaction, so a concurrent move cannot slip a cycle past the check below.
            $current = $this->customerDepartmentRepository->lockForCompany($hqId, $command->customerId, $command->departmentId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($changes->parentSpecified) {
                $this->customerOrgStructureValidator->assertParent($hqId, $command->customerId, $changes->parentDepartmentId, $command->departmentId);
            }
            $this->customerDepartmentRepository->update($hqId, $command->departmentId, $changes->toAttributes());

            return $this->customerDepartmentRepository->findForCompany($hqId, $command->customerId, $command->departmentId) ?? $current;
        }, attempts: 3);

        return new UpdateCustomerDepartmentResult($department);
    }
}
