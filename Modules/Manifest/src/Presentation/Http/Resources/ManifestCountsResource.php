<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ManifestCountsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['pending' => $this->pending, 'validated' => $this->validated, 'succeeded' => $this->succeeded, 'failed' => $this->failed, 'skipped' => $this->skipped];
    }
}
