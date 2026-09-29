<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ServiceCatalog\Application\Serialization\CatalogRecordDocument;

final class CatalogRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return CatalogRecordDocument::make($this->resource);
    }
}
