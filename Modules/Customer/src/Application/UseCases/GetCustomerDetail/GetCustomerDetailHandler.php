<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerDetail;

use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetCustomerDetailHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private TaskRepositoryInterface $taskRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
    ) {}

    public function handle(GetCustomerDetailCommand $command): GetCustomerDetailResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        $customer = $this->customerRepository->findDetailForTenant($hqId, $command->customerId);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if ($customer === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new GetCustomerDetailResult(
            $customer,
            $this->taskRepository->openForCustomer($hqId, $command->customerId),
            $this->opportunityRepository->openForCustomer($hqId, $command->customerId),
        );
    }
}
