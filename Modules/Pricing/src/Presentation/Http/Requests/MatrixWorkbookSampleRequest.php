<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MatrixWorkbookSampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_titles' => ['required', 'array', 'min:1', 'max:100'],
            'zone_titles.*' => ['required', 'string', 'max:200'],
        ];
    }
}
