<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerOrgStructure;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerDepartmentRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetCustomerOrgStructureHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerDepartmentRepositoryInterface $customerDepartmentRepository,
    ) {}

    public function handle(GetCustomerOrgStructureCommand $command): GetCustomerOrgStructureResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        $company = $this->customerRepository->findProfileForTenant($hqId, $command->customerId);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if ($company === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        // A PERSON record simply has an empty chart; reading it is not an error.
        return new GetCustomerOrgStructureResult($company, $this->customerDepartmentRepository->listForCompany($hqId, $command->customerId));
    }
}
