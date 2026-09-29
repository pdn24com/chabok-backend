<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentLinkRecord;

/**
 * What one document is attached to. The pair of type and ID is the whole reference; the title of the
 * record on the other end belongs to that record's own endpoint, not here.
 *
 * @mixin DocumentLinkRecord
 */
final class DocumentLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_link_id' => $this->document_link_id,
            'document_id' => $this->document_id,
            'resource_type' => $this->resource_type->value,
            'resource_id' => $this->resource_id,
            'purpose' => $this->purpose,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
