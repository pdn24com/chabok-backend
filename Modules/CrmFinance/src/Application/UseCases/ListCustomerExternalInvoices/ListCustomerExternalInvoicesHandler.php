<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerExternalInvoices;

use Modules\CrmFinance\Application\Contracts\FinanceAccessGuardInterface;
use Modules\CrmFinance\Application\Repositories\ExternalInvoiceRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ListCustomerExternalInvoicesHandler
{
    public function __construct(
        private FinanceAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ExternalInvoiceRepositoryInterface $externalInvoiceRepository,
    ) {}

    public function handle(ListCustomerExternalInvoicesCommand $command): ListCustomerExternalInvoicesResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new ListCustomerExternalInvoicesResult($this->externalInvoiceRepository->listForCustomer($hqId, $command->customerId));
    }
}
