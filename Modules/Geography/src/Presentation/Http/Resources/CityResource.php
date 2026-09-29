<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;

/** @mixin CityRecord */
final class CityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'city_id' => $this->city_id,
            'legacy_city_code' => (string) $this->legacy_city_code,
            'name_fa' => $this->name_fa,
            'display_name' => sprintf('%s — %s (کد %s)', $this->name_fa, $this->province->name_fa, $this->legacy_city_code),
            'is_active' => (bool) $this->is_active,
            'province' => [
                'province_id' => $this->province_id,
                'legacy_province_code' => (string) $this->province->legacy_province_code,
                'name_fa' => $this->province->name_fa,
            ],
        ];
    }
}
