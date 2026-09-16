<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_code' => ['required', 'string', 'max:80'],
            'plate_number' => ['required', 'string', 'max:40'],
            'vehicle_type' => ['required', 'in:MOTORCYCLE,CAR,VAN,LIGHT_TRUCK,TRUCK,TRAILER,OTHER'],
            'home_node_id' => ['required', 'uuid'],
            'capacity_weight_grams' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'capacity_volume_cm3' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'vehicle_code',
            'plate_number',
            'vehicle_type',
            'home_node_id',
            'capacity_weight_grams',
            'capacity_volume_cm3',
        ];
        StrictPayload::assertOnly($this, $fields);
    }
}
