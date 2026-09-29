<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

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
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'home_node_id' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
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
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.least_one_field_must_be_changed');
        }
    }
}
