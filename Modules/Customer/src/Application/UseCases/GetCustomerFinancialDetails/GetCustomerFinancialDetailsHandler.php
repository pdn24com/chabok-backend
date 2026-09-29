<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerFinancialDetails;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerFinancialDetailRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerFinancialDetailRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetCustomerFinancialDetailsHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerFinancialDetailRepositoryInterface $customerFinancialDetailRepository,
    ) {}

    public function handle(GetCustomerFinancialDetailsCommand $command): GetCustomerFinancialDetailsResult
    {
        // Money sits behind its own permission, so the customer file being readable is not enough.
        $hqId = $this->accessGuard->assertCanReadFinance($command->actor);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        // The row appears on the first save, so a customer nobody assessed yet answers with an empty form.
        return new GetCustomerFinancialDetailsResult($this->customerFinancialDetailRepository->findForCustomer($hqId, $command->customerId)
            ?? (new CustomerFinancialDetailRecord)->forceFill(['customer_id' => $command->customerId]));
    }
}
