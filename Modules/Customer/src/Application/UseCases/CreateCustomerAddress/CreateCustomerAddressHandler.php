<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerAddress;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerAddressValidatorInterface;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CreateCustomerAddressHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerAddressRepositoryInterface $customerAddressRepository,
        private CustomerAddressValidatorInterface $customerAddressValidator,
    ) {}

    public function handle(CreateCustomerAddressCommand $command): CreateCustomerAddressResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        $address = $this->connection->transaction(function () use ($command, $hqId, $input): CustomerAddressRecord {
            if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $this->customerAddressValidator->validateEntry($input);

            // The first entry is the default one whatever the form said: a customer with addresses always
            // has one the rest of the system can fall back to.
            $isDefault = $input->isDefault || ! $this->customerAddressRepository->existsForCustomer($hqId, $command->customerId);
            if ($isDefault) {
                $this->customerAddressRepository->clearDefaults($hqId, $command->customerId);
            }

            $at = $this->clock->now();
            $address = $this->customerAddressRepository->create([
                ...$input->toAttributes(),
                'is_default' => $isDefault,
                'hq_id' => $hqId,
                'customer_id' => $command->customerId,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            // Read back, so the response names the reference province and city instead of their IDs alone.
            return $this->customerAddressRepository->findForCustomer($hqId, $command->customerId, $address->customer_address_id) ?? $address;
        }, attempts: 3);

        return new CreateCustomerAddressResult($address);
    }
}
