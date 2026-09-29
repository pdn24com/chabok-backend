<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Operations\Application\Serialization\FleetDocument;

final class FleetDriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return FleetDocument::driver($this->resource);
    }
}
