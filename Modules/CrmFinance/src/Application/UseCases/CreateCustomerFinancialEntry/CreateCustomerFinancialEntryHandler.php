<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerFinancialEntry;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmFinance\Application\Contracts\FinanceAccessGuardInterface;
use Modules\CrmFinance\Application\Repositories\ExternalInvoiceRepositoryInterface;
use Modules\CrmFinance\Application\Repositories\FinancialEntryRepositoryInterface;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialEntryRecord;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Entries are written once and never edited or deleted, so a mistake is corrected by a fresh entry that
 * names the one it reverses. That keeps the trail of what was believed when, which an in-place edit loses.
 */
final readonly class CreateCustomerFinancialEntryHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private FinanceAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ExternalInvoiceRepositoryInterface $externalInvoiceRepository,
        private FinancialEntryRepositoryInterface $financialEntryRepository,
    ) {}

    public function handle(CreateCustomerFinancialEntryCommand $command): CreateCustomerFinancialEntryResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        $entry = $this->connection->transaction(function () use ($command, $hqId, $input): FinancialEntryRecord {
            // A customer of another tenant is indistinguishable from one that does not exist.
            if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            // The cited invoice must be a document of this same customer; the database refuses anything
            // else outright, and catching it here turns a failed write into an answer the form can show.
            if ($input->invoiceId !== null
                && ! $this->externalInvoiceRepository->belongsToCustomer($hqId, $command->customerId, $input->invoiceId)) {
                throw $this->invalid('invoice_id', 'finance.select_invoice_of_the_same_customer');
            }
            if ($input->reversesId !== null) {
                $this->assertReversible($hqId, $command->customerId, $input->reversesId, $input->kind->value);
            }

            $entry = $this->financialEntryRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $command->customerId,
                'invoice_id' => $input->invoiceId,
                'kind' => $input->kind->value,
                'amount' => $input->amount,
                // A calendar day, so only the date part of the submitted instant is kept.
                'effective_on' => $input->effectiveOn->format('Y-m-d'),
                'source_ref' => $input->sourceRef,
                'reverses_id' => $input->reversesId,
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]);

            // Read back, so the new row answers with the allocated total every other row carries.
            return $this->financialEntryRepository->findForCustomer($hqId, $command->customerId, $entry->financial_entry_id) ?? $entry;
        }, attempts: 3);

        return new CreateCustomerFinancialEntryResult($entry);
    }

    /** A reversal names an entry of the same customer and the same kind, and no entry is reversed twice. */
    private function assertReversible(string $hqId, string $customerId, string $reversesId, string $kind): void
    {
        $reversed = $this->financialEntryRepository->findForCustomer($hqId, $customerId, $reversesId);
        if ($reversed === null) {
            throw $this->invalid('reverses_id', 'finance.select_entry_of_the_same_customer');
        }
        if ($reversed->kind->value !== $kind) {
            throw $this->invalid('reverses_id', 'finance.reversal_must_match_the_reversed_kind');
        }
        if ($this->financialEntryRepository->alreadyReversed($hqId, $reversesId)) {
            throw $this->invalid('reverses_id', 'finance.entry_is_already_reversed');
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
