<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\ListCustomerContracts;

use Modules\CrmSales\Application\Contracts\ContractAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\ContractRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentLinkRepositoryInterface;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Lists the contract file of a customer. The attached documents are read for the whole list at once
 * from the archive's links, so the list costs three reads however many contracts it holds.
 */
final readonly class ListCustomerContractsHandler
{
    public function __construct(
        private ContractAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ContractRepositoryInterface $contractRepository,
        private DocumentLinkRepositoryInterface $documentLinkRepository,
    ) {}

    public function handle(ListCustomerContractsCommand $command): ListCustomerContractsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        $contracts = $this->contractRepository->listForCustomer($hqId, $command->customerId);
        $documents = $this->documentLinkRepository->documentsForResources(
            $hqId,
            DocumentResourceType::CONTRACT,
            $contracts->map(fn ($contract): string => (string) $contract->contract_id)->all(),
        );

        return new ListCustomerContractsResult($contracts, $documents);
    }
}
