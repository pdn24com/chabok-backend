<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerAddresses;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ListCustomerAddressesHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerAddressRepositoryInterface $customerAddressRepository,
    ) {}

    public function handle(ListCustomerAddressesCommand $command): ListCustomerAddressesResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new ListCustomerAddressesResult($this->customerAddressRepository->listForCustomer($hqId, $command->customerId));
    }
}
