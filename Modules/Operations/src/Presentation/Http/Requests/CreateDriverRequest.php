<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_code' => ['required', 'string', 'max:80'],
            'display_name' => ['required', 'string', 'max:200'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'home_node_id' => ['required', 'uuid'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['required', 'in:PICKUP,LINEHAUL,DELIVERY', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fields = ['driver_code', 'display_name', 'user_id', 'home_node_id', 'mobile', 'capabilities'];
        StrictPayload::assertOnly($this, $fields);
    }
}
