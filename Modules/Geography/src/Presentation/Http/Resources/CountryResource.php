<?php

declare(strict_types=1);

namespace Modules\Geography\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;

/** @mixin CountryRecord */
final class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'country_id' => $this->country_id,
            'country_code' => $this->country_code,
            'alpha3_code' => $this->alpha3_code,
            'numeric_code' => $this->numeric_code,
            'name_fa' => $this->name_fa,
            'name_en' => $this->name_en,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
