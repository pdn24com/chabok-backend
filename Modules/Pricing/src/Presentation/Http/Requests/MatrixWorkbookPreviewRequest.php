<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MatrixWorkbookPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content_base64' => ['required', 'string', 'max:6990508'],
            'matrix' => ['required', 'array'],
            'matrix.id' => ['required', 'uuid'],
            'matrix.service_offering_version_id' => ['nullable', 'uuid'],
            'matrix.service_option_version_id' => ['nullable', 'uuid'],
            'matrix.origin_zone_id' => ['nullable', 'uuid'],
            'matrix.zone_ids' => ['required', 'array', 'min:1', 'max:100'],
            'matrix.zone_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
