<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerProfile;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerPrimaryIndustryManagerInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class UpdateCustomerProfileHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerPrimaryIndustryManagerInterface $primaryIndustryManager,
    ) {}

    public function handle(UpdateCustomerProfileCommand $command): UpdateCustomerProfileResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $changes = $command->changes;
        if ($changes->touchesNothing()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['*' => ['customer.profile_change_is_empty']]);
        }

        $customer = $this->connection->transaction(function () use ($hqId, $command, $changes): CustomerRecord {
            // The row is locked for the whole transaction: the record carries no version column, so serialising
            // concurrent writers is the guarantee available until the concurrency decision is settled.
            $current = $this->customerRepository->lockForTenant($hqId, $command->customerId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $at = $this->clock->now();
            $attributes = [
                'kind' => $changes->kind?->value,
                'lifecycle' => $changes->lifecycle?->value,
                'display_name' => $changes->displayName,
                'customer_code' => $changes->customerCode,
                'assignee_id' => $changes->assigneeId,
                'phase' => $changes->phase?->value,
                'updated_at' => $at,
            ];
            // converted_at records the actual first promotion, so a later demotion and re-promotion
            // never overwrites the date the record first became a customer.
            if ($changes->phase === CustomerPhase::CUSTOMER && $current->converted_at === null) {
                $attributes['converted_at'] = $at;
            }
            $this->customerRepository->update($hqId, $command->customerId, $attributes);

            if ($changes->primaryIndustrySpecified) {
                $this->primaryIndustryManager->move(
                    $hqId,
                    $command->customerId,
                    $changes->primaryIndustryId,
                    $command->actor->userId,
                    $at,
                );
            }

            return $this->customerRepository->findProfileForTenant($hqId, $command->customerId) ?? $current;
        }, attempts: 3);

        return new UpdateCustomerProfileResult($customer);
    }
}
