<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerExternalInvoice;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmFinance\Application\Contracts\FinanceAccessGuardInterface;
use Modules\CrmFinance\Application\Repositories\ExternalInvoiceRepositoryInterface;
use Modules\CrmFinance\Infrastructure\Persistence\Models\ExternalInvoiceRecord;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Records that an invoice exists in an accounting system outside the CRM. Nothing is posted here: the
 * reference exists so a receipt can be pointed at the document it settles.
 */
final readonly class CreateCustomerExternalInvoiceHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private FinanceAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
        private ExternalInvoiceRepositoryInterface $externalInvoiceRepository,
    ) {}

    public function handle(CreateCustomerExternalInvoiceCommand $command): CreateCustomerExternalInvoiceResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        $invoice = $this->connection->transaction(function () use ($command, $hqId, $input): ExternalInvoiceRecord {
            // A customer of another tenant is indistinguishable from one that does not exist.
            if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            // One document of one external system is recorded once for the whole tenant, whichever
            // customer it belongs to, so a second reference to it is a conflict rather than a duplicate.
            if ($this->externalInvoiceRepository->referenceTaken($hqId, $input->externalSystem, $input->referenceNo)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'common.requested_change_conflicts_with_existing_data');
            }
            if ($input->dueOn !== null && $input->dueOn < $input->issuedOn) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['due_on' => ['finance.invoice_due_date_cannot_precede_issue_date']]);
            }
            if ($input->opportunityId !== null
                && ! $this->opportunityRepository->existsForCustomer($hqId, $command->customerId, $input->opportunityId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['opportunity_id' => ['finance.select_opportunity_of_the_same_customer']]);
            }

            return $this->externalInvoiceRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $command->customerId,
                // A contract is named only once the contract file exists; nothing writes it yet.
                'contract_id' => null,
                'opportunity_id' => $input->opportunityId,
                'external_system' => $input->externalSystem,
                'reference_no' => $input->referenceNo,
                'amount' => $input->amount,
                // Calendar days, so only the date part of the submitted instants is kept.
                'issued_on' => $input->issuedOn->format('Y-m-d'),
                'due_on' => $input->dueOn?->format('Y-m-d'),
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]);
        }, attempts: 3);

        return new CreateCustomerExternalInvoiceResult($invoice);
    }
}
