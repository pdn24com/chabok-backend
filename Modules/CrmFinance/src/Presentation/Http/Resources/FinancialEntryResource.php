<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmFinance\Infrastructure\Persistence\Models\FinancialEntryRecord;

/**
 * One manually recorded financial line. `allocated` is the sum already set against invoices out of this
 * entry and only ever grows on a receipt; it is read from the allocation rows, never stored on the entry.
 * An entry nothing was allocated out of reports zero rather than nothing, because the sum of no rows is
 * zero and a client comparing it against the amount must not have to special-case it.
 *
 * @mixin FinancialEntryRecord
 */
final class FinancialEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'financial_entry_id' => $this->financial_entry_id,
            'customer_id' => $this->customer_id,
            'kind' => $this->kind?->value,
            'amount' => $this->amount,
            'effective_on' => $this->effective_on?->getTimestamp(),
            'source_ref' => $this->source_ref,
            'invoice_id' => $this->invoice_id,
            'reverses_id' => $this->reverses_id,
            'allocated' => (int) ($this->allocated_total ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
