<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateSalesDocument;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmSales\Application\Contracts\SalesDocumentAccessGuardInterface;
use Modules\CrmSales\Application\Dto\SalesDocumentDraftDto;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentVersionRepositoryInterface;
use Modules\CrmSales\Domain\Enums\SalesDocumentStatus;
use Modules\CrmSales\Domain\Support\SalesDocumentNumber;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Draws up a sales document against an opportunity and opens it on a draft revision. The customer is
 * taken from the opportunity rather than accepted as input, so a quote can never name a party the deal
 * was not about, and it must already be a customer: a price is quoted to a buyer, not to a lead.
 */
final readonly class CreateSalesDocumentHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private SalesDocumentAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
        private SalesDocumentRepositoryInterface $salesDocumentRepository,
        private SalesDocumentVersionRepositoryInterface $salesDocumentVersionRepository,
    ) {}

    public function handle(CreateSalesDocumentCommand $command): CreateSalesDocumentResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;
        $at = $this->clock->now();
        // A document is drawn up to be accepted, so it cannot arrive already out of date.
        if ($input->expiresAt <= $at) {
            throw $this->invalid('expires_at', 'sales.validity_cannot_be_in_the_past');
        }

        return $this->connection->transaction(function () use ($command, $hqId, $input, $at): CreateSalesDocumentResult {
            $opportunity = $this->opportunityRepository->findForTenant($hqId, $input->opportunityId)
                ?? throw $this->invalid('opportunity_id', 'sales.select_opportunity_of_the_tenant');
            $customerId = (string) $opportunity->customer_id;
            if (! $this->customerRepository->isInCustomerPhase($hqId, $customerId)) {
                throw $this->invalid('opportunity_id', 'sales.document_needs_a_promoted_customer');
            }

            $documentNo = $input->documentNo ?? $this->nextDocumentNo($hqId, $input, $at);
            if ($this->salesDocumentRepository->documentNoTaken($hqId, $documentNo)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'sales.document_number_is_already_use',
                    ['document_no' => ['sales.document_number_is_already_use']]);
            }

            $document = $this->salesDocumentRepository->create([
                'hq_id' => $hqId,
                'opportunity_id' => $input->opportunityId,
                'customer_id' => $customerId,
                'document_no' => $documentNo,
                'document_type' => $input->documentType->value,
                'current_version_id' => null,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            $version = $this->salesDocumentVersionRepository->create([
                'hq_id' => $hqId,
                'document_id' => $document->sales_document_id,
                'previous_version_id' => null,
                'version_no' => 1,
                'status' => SalesDocumentStatus::DRAFT->value,
                'expires_at' => $input->expiresAt,
                'currency' => $input->currency,
                'total' => $input->total,
                'terms' => $input->terms,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);
            // The document and its first revision point at each other, and the cycle can only be closed
            // once both rows exist.
            $this->salesDocumentRepository->update($hqId, $document->sales_document_id, [
                'current_version_id' => $version->sales_document_version_id,
            ]);

            $stored = $this->salesDocumentRepository->findForTenant($hqId, $document->sales_document_id) ?? $document;

            return new CreateSalesDocumentResult($stored, $version);
        }, attempts: 3);
    }

    /**
     * The next number of this type and year for the tenant. Two writers racing here both read the same
     * counter; the tenant-wide unique index is what refuses the loser, which surfaces as a conflict.
     */
    private function nextDocumentNo(string $hqId, SalesDocumentDraftDto $input, DateTimeImmutable $at): string
    {
        $stem = SalesDocumentNumber::stem($input->documentType, (int) $at->format('Y'));

        return SalesDocumentNumber::format(
            $input->documentType,
            (int) $at->format('Y'),
            $this->salesDocumentRepository->lastSequenceForStem($hqId, $stem) + 1,
        );
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
