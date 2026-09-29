<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

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
            'matrix.id' => ['required', 'string', 'max:64'],
            'matrix.service_offering_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'matrix.service_option_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'matrix.origin_zone_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'matrix.zone_ids' => ['required', 'array', 'min:1', 'max:100'],
            'matrix.zone_ids.*' => ['required', 'integer', 'min:0', 'max:4294967295', 'distinct'],
        ];
    }
}
