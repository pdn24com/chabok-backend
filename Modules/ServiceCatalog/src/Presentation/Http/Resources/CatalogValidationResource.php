<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ServiceCatalog\Application\Serialization\CatalogValidationDocument;

final class CatalogValidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return CatalogValidationDocument::make($this->resource);
    }
}
