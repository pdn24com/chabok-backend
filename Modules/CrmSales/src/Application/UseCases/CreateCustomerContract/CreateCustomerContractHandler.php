<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateCustomerContract;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmSales\Application\Contracts\ContractAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\ContractRepositoryInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentVersionRepositoryInterface;
use Modules\CrmSales\Infrastructure\Persistence\Models\ContractRecord;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Records the contract signed with a customer. Like a sales document it is drawn up with a buyer, so
 * the record must already be a customer. The schema only indexes the reference number, so the one
 * uniqueness rule here is the exact repeat for the same customer, and the customer row is locked to
 * keep two concurrent posts from both passing it. The attached files are not uploaded here: they are
 * linked afterwards through the document archive.
 */
final readonly class CreateCustomerContractHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private ContractAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
        private SalesDocumentVersionRepositoryInterface $salesDocumentVersionRepository,
        private ContractRepositoryInterface $contractRepository,
    ) {}

    public function handle(CreateCustomerContractCommand $command): CreateCustomerContractResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        $contract = $this->connection->transaction(function () use ($command, $hqId, $input): ContractRecord {
            // A customer of another tenant is indistinguishable from one that does not exist.
            if ($this->customerRepository->lockForTenant($hqId, $command->customerId) === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if (! $this->customerRepository->isInCustomerPhase($hqId, $command->customerId)) {
                throw $this->invalid('customer_id', 'contract.needs_a_promoted_customer');
            }
            if ($input->startDate !== null && $input->endDate !== null && $input->endDate < $input->startDate) {
                throw $this->invalid('end_date', 'contract.end_cannot_precede_start');
            }
            if ($input->opportunityId !== null
                && ! $this->opportunityRepository->existsForCustomer($hqId, $command->customerId, $input->opportunityId)) {
                throw $this->invalid('opportunity_id', 'contract.select_opportunity_of_the_same_customer');
            }
            // The proforma the contract was built on must belong to the customer, as the database also insists.
            if ($input->proformaVersionId !== null
                && ! $this->salesDocumentVersionRepository->existsForCustomer($hqId, $command->customerId, $input->proformaVersionId)) {
                throw $this->invalid('proforma_version_id', 'contract.select_proforma_of_the_same_customer');
            }
            if ($this->contractRepository->referenceTaken($hqId, $command->customerId, $input->referenceNo)) {
                throw new ApiException(ApiErrorCode::ContractReferenceExists, 409, 'contract.reference_already_exists',
                    ['reference_no' => ['contract.reference_already_exists']]);
            }

            $created = $this->contractRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $command->customerId,
                'opportunity_id' => $input->opportunityId,
                'proforma_version_id' => $input->proformaVersionId,
                'reference_no' => $input->referenceNo,
                // Calendar days, so only the date part of the submitted instants is kept.
                'start_date' => $input->startDate?->format('Y-m-d'),
                'end_date' => $input->endDate?->format('Y-m-d'),
                'amount' => $input->amount,
                'commitments' => $input->commitments,
                'status' => $input->status,
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]);

            return $this->contractRepository->findForTenant($hqId, $created->contract_id) ?? $created;
        }, attempts: 3);

        return new CreateCustomerContractResult($contract);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
