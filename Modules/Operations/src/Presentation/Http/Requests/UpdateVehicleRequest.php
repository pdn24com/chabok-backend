<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;

final class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate_number' => ['sometimes', 'string', 'max:40'],
            'vehicle_type' => ['sometimes', 'in:MOTORCYCLE,CAR,VAN,LIGHT_TRUCK,TRUCK,TRAILER,OTHER'],
            'home_node_id' => ['sometimes', 'uuid'],
            'capacity_weight_grams' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'capacity_volume_cm3' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'availability_status' => ['sometimes', 'in:AVAILABLE,ON_MISSION,TEMPORARILY_INACTIVE,MAINTENANCE,INACTIVE'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'plate_number',
            'vehicle_type',
            'home_node_id',
            'capacity_weight_grams',
            'capacity_volume_cm3',
            'status',
            'availability_status',
            'expected_version',
        ];
        StrictPayload::assertOnly($this, $fields);
    }

    protected function passedValidation(): void
    {
        if (count($this->validated()) < 2) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'At least one field must be changed.');
        }
    }
}
