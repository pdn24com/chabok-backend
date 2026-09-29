<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesCommand;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesHandler;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ListOperationalStatusCodesHandler $statuses): array
    {
        return [
            'expected_version' => ['required', 'integer', 'in:0'],
            'manifest_status' => ['required', Rule::in($statuses->handle(new ListOperationalStatusCodesCommand($this->attributes->get('principal')->hqId, true)))],
            'context_key' => ['required', 'string', 'min:1', 'max:200'],
            'target_node_id' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
            'assigned_driver_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'assigned_vehicle_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
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
