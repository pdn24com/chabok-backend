<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\AllocateReceiptToInvoice;

use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialAllocationRecord;

/** The stored allocation, carrying what is left of the receipt after it. */
final readonly class AllocateReceiptToInvoiceResult
{
    public function __construct(
        public FinancialAllocationRecord $allocation,
    ) {}
}
