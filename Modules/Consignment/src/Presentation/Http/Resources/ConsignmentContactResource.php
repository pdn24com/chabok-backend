<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Geography\Presentation\Http\Resources\CityResource;

final class ConsignmentContactResource extends JsonResource
{
    public function __construct(ConsignmentRecord $consignment, private readonly string $prefix)
    {
        parent::__construct($consignment);
    }

    public function toArray(Request $request): array
    {
        $result = ['address_book_entry_id' => null];
        foreach (['contact_name', 'mobile', 'phone', 'address_text', 'country', 'state', 'city', 'city_id', 'postal_code', 'latitude', 'longitude'] as $field) {
            $value = $this->resource->{$this->prefix.'_'.$field};
            $result[$field] = in_array($field, ['latitude', 'longitude'], true) && $value !== null ? (float) $value : $value;
        }
        $city = $this->resource->{$this->prefix.'City'};
        $result['city_reference'] = $city === null ? null : (new CityResource($city))->resolve($request);

        return $result;
    }
}
