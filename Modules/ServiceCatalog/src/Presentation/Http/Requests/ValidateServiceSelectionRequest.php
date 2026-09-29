<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ValidateServiceSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_offering_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'channel' => ['required', 'in:BRANCH,VENDOR,DRIVER,HQ,API,TRACKING,LEGACY'],
            'as_of_timestamp' => ['nullable', 'date'],
            'acceptance_at' => ['nullable', 'date'],
            'pickup_window_code' => ['nullable', 'string', 'max:80'],
            'pickup_service_date' => ['nullable', 'date_format:Y-m-d'],
            'delivery_window_code' => ['nullable', 'string', 'max:80'],
            'sender' => ['required', 'array'],
            'receiver' => ['required', 'array'],
            'parcels' => ['nullable', 'array'],
            'selected_option_version_ids' => ['nullable', 'array'],
            'selected_option_version_ids.*' => ['integer', 'min:1', 'max:4294967295'],
        ];
    }
}
