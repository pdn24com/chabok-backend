<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerDepartment;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerOrgStructureValidatorInterface;
use Modules\Customer\Application\Repositories\CustomerDepartmentRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;

final readonly class CreateCustomerDepartmentHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerDepartmentRepositoryInterface $customerDepartmentRepository,
        private CustomerOrgStructureValidatorInterface $customerOrgStructureValidator,
    ) {}

    public function handle(CreateCustomerDepartmentCommand $command): CreateCustomerDepartmentResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);

        $department = $this->connection->transaction(function () use ($command, $hqId): CustomerDepartmentRecord {
            $this->customerOrgStructureValidator->assertCompany($hqId, $command->customerId);
            $this->customerOrgStructureValidator->assertParent($hqId, $command->customerId, $command->input->parentDepartmentId);

            $department = $this->customerDepartmentRepository->create([
                ...$command->input->toAttributes(),
                'hq_id' => $hqId,
                'company_customer_id' => $command->customerId,
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]);

            // Read back, so a new node answers with the same shape the chart renders.
            return $this->customerDepartmentRepository->findForCompany($hqId, $command->customerId, $department->customer_department_id) ?? $department;
        }, attempts: 3);

        return new CreateCustomerDepartmentResult($department);
    }
}
