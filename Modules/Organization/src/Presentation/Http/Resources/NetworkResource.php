<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Organization\Application\Serialization\NetworkDocument;

final class NetworkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return NetworkDocument::serialize($this->resource);
    }
}
