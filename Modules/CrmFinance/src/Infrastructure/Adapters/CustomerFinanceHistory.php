<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Adapters;

use BackedEnum;
use Modules\CrmFinance\Application\Repositories\ExternalInvoiceRepositoryInterface;
use Modules\CrmFinance\Application\Repositories\FinancialEntryRepositoryInterface;
use Modules\Customer\Application\Dto\CustomerHistoryEntryDto;
use Modules\Customer\Application\Ports\CustomerFinanceHistoryInterface;
use Modules\Customer\Domain\Enums\CustomerHistoryCategory;

/**
 * The finance rows of the customer history page. CrmFinance already reads Customer to prove the customer
 * of an invoice, so it fills the Customer port here rather than being read back the other way.
 */
final readonly class CustomerFinanceHistory implements CustomerFinanceHistoryInterface
{
    public function __construct(
        private ExternalInvoiceRepositoryInterface $externalInvoiceRepository,
        private FinancialEntryRepositoryInterface $financialEntryRepository,
    ) {}

    public function historyForCustomer(string $hqId, string $customerId): array
    {
        $rows = [];
        foreach ($this->externalInvoiceRepository->listForCustomer($hqId, $customerId) as $invoice) {
            $rows[] = new CustomerHistoryEntryDto(
                category: CustomerHistoryCategory::FINANCE,
                entryType: 'EXTERNAL_INVOICE',
                entryId: $invoice->external_invoice_id,
                title: $invoice->external_system.' '.$invoice->reference_no,
                occurredAt: $invoice->issued_on,
                kind: 'EXTERNAL_INVOICE',
                amount: $invoice->amount,
            );
        }
        foreach ($this->financialEntryRepository->listForCustomer($hqId, $customerId) as $entry) {
            $rows[] = new CustomerHistoryEntryDto(
                category: CustomerHistoryCategory::FINANCE,
                entryType: 'FINANCIAL_ENTRY',
                entryId: $entry->financial_entry_id,
                title: $entry->source_ref,
                occurredAt: $entry->effective_on,
                kind: $entry->kind instanceof BackedEnum ? $entry->kind->value : (string) $entry->kind,
                amount: $entry->amount,
            );
        }

        return $rows;
    }
}
