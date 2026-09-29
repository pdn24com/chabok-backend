<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ServiceCatalog\Application\Dto\CommitmentZoneSummaryDto;

final class CommitmentZoneGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['pricing_zone_set_id' => $this->resource->id, 'zone_set_version_id' => $this->versionId,
            'code' => $this->code, 'title' => $this->title,
            'zones' => array_map(static fn (CommitmentZoneSummaryDto $zone): array => ['code' => $zone->code, 'title' => $zone->title], $this->zones)];
    }
}
