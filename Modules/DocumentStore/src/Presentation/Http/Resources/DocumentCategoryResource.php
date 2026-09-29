<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentCategoryRecord;

/**
 * One node of the category tree. The tree is served flat with each node naming its parent, so a client
 * builds whatever shape it draws without the server deciding the nesting for it.
 *
 * @mixin DocumentCategoryRecord
 */
final class DocumentCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_category_id' => $this->document_category_id,
            'parent_id' => $this->parent_id,
            'title' => $this->title,
            'active' => $this->active,
        ];
    }
}
