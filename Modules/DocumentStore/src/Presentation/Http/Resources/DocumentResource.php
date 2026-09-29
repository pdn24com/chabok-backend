<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentRecord;

/**
 * One document of the archive. expires_on travels as a unix timestamp in seconds, midnight UTC of the
 * stored calendar day. The category and the links appear only where their relations were read.
 *
 * Every link of the document is listed, including the ones to records other than the one the list was
 * narrowed by: they share a tenant and the same permission, and knowing where else a document is filed
 * is the reason the archive is shared in the first place.
 *
 * @mixin DocumentRecord
 */
final class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_id' => $this->document_id,
            'title' => $this->title,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null
                : (new DocumentCategoryResource($this->category))->resolve($request)),
            'classification' => $this->classification,
            'reference_no' => $this->reference_no,
            'expires_on' => $this->expires_on?->getTimestamp(),
            'status' => $this->status->value,
            'links' => $this->whenLoaded('links', fn (): array => DocumentLinkResource::collection($this->links)->resolve($request)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
