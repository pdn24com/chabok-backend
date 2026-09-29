<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;

/**
 * One revision of a sales document. content_hash and customer_snapshot stay null until it is issued;
 * from then on they are the proof of what was agreed and never change again.
 *
 * @mixin SalesDocumentVersionRecord
 */
final class SalesDocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sales_document_version_id' => $this->sales_document_version_id,
            'version_no' => (int) $this->version_no,
            'previous_version_id' => $this->previous_version_id,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'total' => (int) $this->total,
            'terms' => $this->terms,
            'expires_at' => $this->expires_at?->toISOString(),
            'issued_at' => $this->issued_at?->toISOString(),
            'content_hash' => $this->content_hash,
            'customer_snapshot' => $this->customer_snapshot,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
