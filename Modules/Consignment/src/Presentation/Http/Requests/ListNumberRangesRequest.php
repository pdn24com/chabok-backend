<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListNumberRangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'in:AVAILABLE,EXHAUSTED,DISABLED'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:250'],
        ];
    }
}
