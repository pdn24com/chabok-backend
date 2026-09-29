<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ChangeCustomerLifecycle;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Repositories\ActivityRepositoryInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ChangeCustomerLifecycleHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ActivityRepositoryInterface $activityRepository,
    ) {}

    public function handle(ChangeCustomerLifecycleCommand $command): ChangeCustomerLifecycleResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $reason = $command->reason === null || trim($command->reason) === '' ? null : trim($command->reason);
        // Closing or archiving a record without saying why would leave no trace of the decision.
        if ($reason === null && $command->lifecycle !== CustomerLifecycle::ACTIVE) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['reason' => ['customer.a_reason_is_required_to_leave_the_active_state']]);
        }

        return $this->connection->transaction(function () use ($hqId, $command, $reason): ChangeCustomerLifecycleResult {
            $current = $this->customerRepository->lockForTenant($hqId, $command->customerId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($current->lifecycle === $command->lifecycle->value) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['lifecycle' => ['customer.lifecycle_is_already_set']]);
            }

            $at = $this->clock->now();
            $this->customerRepository->update($hqId, $command->customerId, ['lifecycle' => $command->lifecycle->value, 'updated_at' => $at]);

            // The reason lives in the interaction log of the customer, as a note.
            $activity = $reason === null ? null : $this->activityRepository->create([
                'hq_id' => $hqId,
                'type' => 'NOTE',
                'occurred_at' => $at,
                'customer_id' => $command->customerId,
                'body' => $reason,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            return new ChangeCustomerLifecycleResult($command->customerId, $command->lifecycle, $activity?->activity_id);
        }, attempts: 3);
    }
}
