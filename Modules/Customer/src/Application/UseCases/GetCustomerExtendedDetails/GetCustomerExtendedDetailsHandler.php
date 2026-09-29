<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerExtendedDetails;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerExtendedDetailRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerExtendedDetailRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetCustomerExtendedDetailsHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerExtendedDetailRepositoryInterface $customerExtendedDetailRepository,
    ) {}

    public function handle(GetCustomerExtendedDetailsCommand $command): GetCustomerExtendedDetailsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        // The row appears on the first save, so a customer nobody filled in yet answers with an empty form
        // rather than a 404: the form has somewhere to load from either way.
        return new GetCustomerExtendedDetailsResult($this->customerExtendedDetailRepository->findForCustomer($hqId, $command->customerId)
            ?? (new CustomerExtendedDetailRecord)->forceFill(['customer_id' => $command->customerId]));
    }
}
