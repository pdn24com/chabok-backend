<?php

declare(strict_types=1);

namespace Modules\Organization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class NodeInputRequest extends FormRequest
{
    abstract protected function creating(): bool;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->creating();
        $required = $creating ? 'required' : 'sometimes';
        return [
            'area_id' => [$required, 'uuid'],
            'node_code' => [$creating ? 'required' : 'prohibited', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'node_title' => [$required, 'string', 'max:200'],
            'node_type' => [$required, 'in:BRANCH,HUB,GATEWAY,AGENT'],
            'capabilities' => [$required, 'array'],
            'capabilities.*' => ['string', 'distinct', 'in:PICKUP,CONSOLIDATION,GATEWAY,LINEHAUL,DELIVERY,CUSTOMER_HANDOFF'],
            'address' => [$required, 'array'],
            'address.country_code' => [$required, 'in:IR'],
            'address.province_id' => ['sometimes', 'nullable', 'uuid'],
            'address.city_id' => ['sometimes', 'nullable', 'uuid'],
            'address.postal_code' => ['sometimes', 'nullable', 'regex:/^[0-9]{10}$/'],
            'address.line' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'address.location' => ['sometimes', 'nullable', 'array'],
            'address.location.latitude' => ['required_with:address.location', 'numeric', 'between:-90,90'],
            'address.location.longitude' => ['required_with:address.location', 'numeric', 'between:-180,180'],
            'status' => [$creating ? 'prohibited' : 'sometimes', 'in:ACTIVE,INACTIVE'],
            'expected_version' => [$creating ? 'prohibited' : 'required', 'integer', 'min:1'],
        ];
    }

    protected function passedValidation(): void
    {
        if (!$this->creating() && count($this->validated()) === 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['request' => ['At least one mutable field is required.']]);
        }
    }
}
