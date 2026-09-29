<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Validation\ValidationException;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

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
            'parent_area_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if (count($input) === 1) {
            throw ValidationException::withMessages(['request' => ['At least one mutable field is required.']]);
        }
    }
}
