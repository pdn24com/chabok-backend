<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;

/**
 * A row of the sales document table: what the document is, who it is for and where the revision it
 * currently shows has got to.
 *
 * @mixin SalesDocumentRecord
 */
final class SalesDocumentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->customer;
        $version = $this->currentVersion;

        return [
            'sales_document_id' => $this->sales_document_id,
            'document_no' => $this->document_no,
            'document_type' => $this->document_type->value,
            'customer' => $customer === null ? null : [
                'customer_id' => $customer->customer_id,
                'display_name' => $customer->display_name,
            ],
            'current_version' => $version === null ? null : [
                'sales_document_version_id' => $version->sales_document_version_id,
                'version_no' => (int) $version->version_no,
                'status' => $version->status->value,
                'currency' => $version->currency,
                'total' => (int) $version->total,
                'expires_at' => $version->expires_at?->toISOString(),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
