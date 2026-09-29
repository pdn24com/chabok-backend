<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerFinancialEntries;

use Modules\CrmFinance\Application\Contracts\FinanceAccessGuardInterface;
use Modules\CrmFinance\Application\Repositories\FinancialEntryRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ListCustomerFinancialEntriesHandler
{
    public function __construct(
        private FinanceAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private FinancialEntryRepositoryInterface $financialEntryRepository,
    ) {}

    public function handle(ListCustomerFinancialEntriesCommand $command): ListCustomerFinancialEntriesResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new ListCustomerFinancialEntriesResult($this->financialEntryRepository->listForCustomer($hqId, $command->customerId, $command->filters->kind));
    }
}
