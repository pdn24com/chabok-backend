<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerPosition;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerDepartmentRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerPositionRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerPositionRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CreateCustomerPositionHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerDepartmentRepositoryInterface $customerDepartmentRepository,
        private CustomerPositionRepositoryInterface $customerPositionRepository,
    ) {}

    public function handle(CreateCustomerPositionCommand $command): CreateCustomerPositionResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);

        $position = $this->connection->transaction(function () use ($command, $hqId): CustomerPositionRecord {
            // Reading the node through its company is the ownership check: a node of another company or
            // another tenant is indistinguishable from one that does not exist.
            if ($this->customerDepartmentRepository->findForCompany($hqId, $command->customerId, $command->departmentId) === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }

            return $this->customerPositionRepository->create([
                ...$command->input->toAttributes(),
                'hq_id' => $hqId,
                'department_id' => $command->departmentId,
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]);
        }, attempts: 3);

        return new CreateCustomerPositionResult($position);
    }
}
