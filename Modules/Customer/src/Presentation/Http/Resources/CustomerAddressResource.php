<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerAddressRecord;

/**
 * One stored address, wherever one is returned: the address book of a customer and the location the
 * create-customer response echoes back. The reference country, province and city appear as named places
 * only where they were read; elsewhere the stored code and the two IDs carry the same reference alone.
 *
 * @mixin CustomerAddressRecord
 */
final class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_address_id' => $this->customer_address_id,
            'country_code' => $this->country_code,
            'country' => $this->whenLoaded('country', fn (): ?array => $this->country === null ? null
                : ['country_id' => $this->country->country_id, 'name' => $this->country->name_fa]),
            'purpose' => $this->purpose,
            'province_id' => $this->province_id,
            'city_id' => $this->city_id,
            'province' => $this->whenLoaded('province', fn (): ?array => $this->province === null ? null
                : ['province_id' => $this->province->province_id, 'name' => $this->province->name_fa]),
            'city' => $this->whenLoaded('city', fn (): ?array => $this->city === null ? null
                : ['city_id' => $this->city->city_id, 'name' => $this->city->name_fa]),
            'foreign_region' => $this->foreign_region,
            'foreign_city' => $this->foreign_city,
            'address_text' => $this->address_text,
            'postal_code' => $this->postal_code,
            'plaque' => $this->plaque,
            'unit' => $this->unit,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
