<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Operations\Application\Serialization\CoveragePolicyDocument;

final class CoverageVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return CoveragePolicyDocument::version($this->resource);
    }
}
