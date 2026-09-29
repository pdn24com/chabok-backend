<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\AllocateReceiptToInvoice;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmFinance\Application\Contracts\FinanceAccessGuardInterface;
use Modules\CrmFinance\Application\Repositories\ExternalInvoiceRepositoryInterface;
use Modules\CrmFinance\Application\Repositories\FinancialAllocationRepositoryInterface;
use Modules\CrmFinance\Application\Repositories\FinancialEntryRepositoryInterface;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialAllocationRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Sets part of one receipt against one invoice. The rule that the allocated total never passes the
 * receipt amount needs the sum of the sibling rows, which no row-level constraint can express, so the
 * receipt is locked for the length of the write and the sum is read under that lock.
 */
final readonly class AllocateReceiptToInvoiceHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private FinanceAccessGuardInterface $accessGuard,
        private ExternalInvoiceRepositoryInterface $externalInvoiceRepository,
        private FinancialEntryRepositoryInterface $financialEntryRepository,
        private FinancialAllocationRepositoryInterface $financialAllocationRepository,
    ) {}

    public function handle(AllocateReceiptToInvoiceCommand $command): AllocateReceiptToInvoiceResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        $allocation = $this->connection->transaction(function () use ($command, $hqId, $input): FinancialAllocationRecord {
            // An entry of another tenant is indistinguishable from one that does not exist.
            $receipt = $this->financialEntryRepository->lockForTenant($hqId, $command->entryId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');

            if ($receipt->kind !== FinancialEntryKind::RECEIPT) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['*' => ['finance.only_a_receipt_can_be_allocated']]);
            }
            if (! $this->externalInvoiceRepository->belongsToCustomer($hqId, $receipt->customer_id, $input->invoiceId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['invoice_id' => ['finance.select_invoice_of_the_same_customer']]);
            }

            // Read under the lock taken above, so two operators allocating at once cannot both fit.
            $remaining = $receipt->amount - $this->financialAllocationRepository->allocatedTotalForEntry($hqId, $command->entryId);
            if ($input->amount > $remaining) {
                throw new ApiException(ApiErrorCode::AllocationExceedsReceipt, 422, 'finance.allocation_exceeds_the_receipt',
                    details: ['remaining' => $remaining]);
            }

            $allocation = $this->financialAllocationRepository->create([
                'hq_id' => $hqId,
                'receipt_entry_id' => $command->entryId,
                'invoice_id' => $input->invoiceId,
                'amount' => $input->amount,
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]);

            // What is left of the receipt afterwards, so the form need not ask for the ledger again.
            return $allocation->setAttribute('remaining_amount', $remaining - $input->amount);
        }, attempts: 3);

        return new AllocateReceiptToInvoiceResult($allocation);
    }
}
