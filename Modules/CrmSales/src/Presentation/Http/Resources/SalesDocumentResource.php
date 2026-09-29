<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;

/**
 * One sales document with the revision it currently shows and every revision it has been through, which
 * is what the drawer of the sales screen reads.
 *
 * @mixin SalesDocumentRecord
 */
final class SalesDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->customer;
        $opportunity = $this->opportunity;
        $version = $this->currentVersion;

        return [
            'sales_document_id' => $this->sales_document_id,
            'document_no' => $this->document_no,
            'document_type' => $this->document_type->value,
            'customer' => $customer === null ? null : [
                'customer_id' => $customer->customer_id,
                'display_name' => $customer->display_name,
                'customer_code' => $customer->customer_code,
            ],
            'opportunity' => $opportunity === null ? null : [
                'opportunity_id' => $opportunity->opportunity_id,
                'title' => $opportunity->title,
            ],
            'current_version' => $version === null ? null : (new SalesDocumentVersionResource($version))->resolve($request),
            'versions' => $this->versions
                ->map(fn (SalesDocumentVersionRecord $row): array => (new SalesDocumentVersionResource($row))->resolve($request))
                ->all(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
