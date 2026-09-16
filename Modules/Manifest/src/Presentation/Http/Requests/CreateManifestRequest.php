<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(\Modules\Consignment\Application\OperationalStatusCatalog $statuses): array
    {
        return [
            'expected_version' => ['required', 'integer', 'in:0'],
            'manifest_status' => [
                'required',
                \Illuminate\Validation\Rule::in($statuses->codes($this->attributes->get('principal')->hqId, true)),
            ],
            'context_key' => ['required', 'string', 'min:1', 'max:200'],
            'target_node_id' => ['sometimes', 'uuid'],
            'assigned_driver_id' => ['sometimes', 'nullable', 'uuid'],
            'assigned_vehicle_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, [
            'expected_version',
            'manifest_status',
            'context_key',
            'target_node_id',
            'assigned_driver_id',
            'assigned_vehicle_id',
        ]);
    }
}
