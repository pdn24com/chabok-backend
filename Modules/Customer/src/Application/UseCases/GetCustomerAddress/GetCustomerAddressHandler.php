<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerAddress;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetCustomerAddressHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerAddressRepositoryInterface $customerAddressRepository,
    ) {}

    public function handle(GetCustomerAddressCommand $command): GetCustomerAddressResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        $address = $this->customerAddressRepository->findForCustomer($hqId, $command->customerId, $command->addressId);
        // An address of another tenant, or of another customer, reads as one that does not exist.
        if ($address === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new GetCustomerAddressResult($address);
    }
}
