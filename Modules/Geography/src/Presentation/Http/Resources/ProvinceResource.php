<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;

/** @mixin ProvinceRecord */
final class ProvinceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'province_id' => $this->province_id,
            'legacy_province_code' => (string) $this->legacy_province_code,
            'name_fa' => $this->name_fa,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
