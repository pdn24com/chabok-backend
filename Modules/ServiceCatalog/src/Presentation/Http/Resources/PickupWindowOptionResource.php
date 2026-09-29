<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ServiceCatalog\Application\Serialization\CommitmentResolutionDocument;

final class PickupWindowOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['commitment_schedule_version_id' => $this->scheduleVersionId, ...CommitmentResolutionDocument::window($this->window)];
    }
}
