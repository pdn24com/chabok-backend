<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;

final class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'string', 'max:200'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'home_node_id' => ['sometimes', 'uuid'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'capabilities' => ['sometimes', 'array', 'min:1'],
            'capabilities.*' => ['required_with:capabilities', 'in:PICKUP,LINEHAUL,DELIVERY', 'distinct'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'availability_status' => ['sometimes', 'in:AVAILABLE,ON_MISSION,TEMPORARILY_INACTIVE,MAINTENANCE,INACTIVE'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'display_name',
            'user_id',
            'home_node_id',
            'mobile',
            'capabilities',
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
