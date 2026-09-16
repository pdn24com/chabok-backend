<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area_title' => ['sometimes', 'string', 'max:200'],
            'parent_area_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if (count($input) === 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['request' => ['At least one mutable field is required.']]);
        }
    }
}
