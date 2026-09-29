<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerFinancialDetailRecord;

/**
 * The manual financial summary the form loads and sends back. financial_reference_date travels as a unix
 * timestamp in seconds, midnight UTC of the stored calendar day. Nothing here is derived from the
 * financial entries, so a client must not expect the two to agree.
 *
 * @mixin CustomerFinancialDetailRecord
 */
final class CustomerFinancialDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this->customer_id,
            'credit_limit' => $this->credit_limit,
            'credit_rating' => $this->credit_rating?->value,
            'settlement_terms' => $this->settlement_terms,
            'financial_reference_date' => $this->financial_reference_date?->getTimestamp(),
            'source_note' => $this->source_note,
            'accounting_code' => $this->accounting_code,
            'accounting_title' => $this->accounting_title,
            'revenue' => $this->revenue,
            'receipts' => $this->receipts,
            'direct_cost' => $this->direct_cost,
            'balance' => $this->balance,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
