<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

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
            'home_node_id' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
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
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.least_one_field_must_be_changed');
        }
    }
}
