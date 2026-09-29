<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerPosition;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerPositionRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerPositionRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class UpdateCustomerPositionHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerPositionRepositoryInterface $customerPositionRepository,
    ) {}

    public function handle(UpdateCustomerPositionCommand $command): UpdateCustomerPositionResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $changes = $command->changes;
        if ($changes->touchesNothing()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['*' => ['customer.position_change_is_empty']]);
        }

        $position = $this->connection->transaction(function () use ($command, $hqId, $changes): CustomerPositionRecord {
            $current = $this->customerPositionRepository->lockForCompany($hqId, $command->customerId, $command->positionId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $this->customerPositionRepository->update($hqId, $command->positionId, $changes->toAttributes());

            return $this->customerPositionRepository->findForCompany($hqId, $command->customerId, $command->positionId) ?? $current;
        }, attempts: 3);

        return new UpdateCustomerPositionResult($position);
    }
}
