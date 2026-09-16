<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;

final class UpdateManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(\Modules\Consignment\Application\OperationalStatusCatalog $statuses): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'manifest_status' => [
                'sometimes',
                \Illuminate\Validation\Rule::in($statuses->codes($this->attributes->get('principal')->hqId, true)),
            ],
            'context_key' => ['sometimes', 'string', 'min:1', 'max:200'],
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

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if (count($input) === 1) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'A context change is required.');
        }
    }
}
