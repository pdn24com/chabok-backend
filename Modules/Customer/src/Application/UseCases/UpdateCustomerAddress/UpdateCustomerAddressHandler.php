<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerAddress;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerAddressValidatorInterface;
use Modules\Customer\Application\Dto\CustomerAddressDraftDto;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class UpdateCustomerAddressHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerAddressRepositoryInterface $customerAddressRepository,
        private CustomerAddressValidatorInterface $customerAddressValidator,
    ) {}

    public function handle(UpdateCustomerAddressCommand $command): UpdateCustomerAddressResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $changes = $command->changes;
        if ($changes->touchesNothing()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['*' => ['customer.address_change_is_empty']]);
        }

        $address = $this->connection->transaction(function () use ($hqId, $command, $changes): CustomerAddressRecord {
            // The row is locked for the whole transaction: the record carries no version column, so
            // serialising concurrent writers is the guarantee available here.
            $current = $this->customerAddressRepository->lockForCustomer($hqId, $command->customerId, $command->addressId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }

            // A partial change is judged as the whole address it leaves behind: switching the country
            // alone can put a reference city or a foreign city on the wrong side of the border.
            $merged = $changes->applyTo(CustomerAddressDraftDto::fromRecord($current));
            $this->customerAddressValidator->validateEntry($merged);

            if ($current->is_default && ! $merged->isDefault) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['is_default' => ['customer.default_address_is_moved_not_cleared']]);
            }
            if ($merged->isDefault) {
                $this->customerAddressRepository->clearDefaults($hqId, $command->customerId, $command->addressId);
            }

            $this->customerAddressRepository->update($hqId, $command->addressId,
                [...$merged->toAttributes(), 'updated_at' => $this->clock->now()]);

            return $this->customerAddressRepository->findForCustomer($hqId, $command->customerId, $command->addressId) ?? $current;
        }, attempts: 3);

        return new UpdateCustomerAddressResult($address);
    }
}
