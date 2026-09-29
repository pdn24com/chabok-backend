<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmFinance\Infrastructure\Persistence\Models\ExternalInvoiceRecord;

/**
 * A reference to an invoice issued outside the CRM. issued_on and due_on travel as unix timestamps in
 * seconds, midnight UTC of the stored calendar day.
 *
 * @mixin ExternalInvoiceRecord
 */
final class ExternalInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'external_invoice_id' => $this->external_invoice_id,
            'customer_id' => $this->customer_id,
            'external_system' => $this->external_system,
            'reference_no' => $this->reference_no,
            'amount' => $this->amount,
            'issued_on' => $this->issued_on?->getTimestamp(),
            'due_on' => $this->due_on?->getTimestamp(),
            'contract_id' => $this->contract_id,
            'opportunity_id' => $this->opportunity_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
