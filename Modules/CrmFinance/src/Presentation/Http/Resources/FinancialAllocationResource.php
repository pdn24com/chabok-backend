<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialAllocationRecord;

/**
 * One allocation of a receipt against an invoice, with what is left of the receipt afterwards so the form
 * need not read the ledger again to show it.
 *
 * @mixin FinancialAllocationRecord
 */
final class FinancialAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'financial_allocation_id' => $this->financial_allocation_id,
            'receipt_entry_id' => $this->receipt_entry_id,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->amount,
            'remaining' => $this->remaining_amount,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
