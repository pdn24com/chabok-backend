<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetCatalogRecordActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['active' => ['required', 'boolean'], 'expected_version' => ['required', 'integer', 'min:1']];
    }
}
