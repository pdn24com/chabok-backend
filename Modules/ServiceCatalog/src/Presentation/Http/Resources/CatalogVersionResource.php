<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;
use Modules\ServiceCatalog\Application\Serialization\ScheduleDocument;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final class CatalogVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource instanceof CommitmentScheduleVersionRecord ? ScheduleDocument::version($this->resource) : CatalogDocument::version($this->resource);
    }
}
