<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ReplacePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['permission_codes']);
    }

    public function rules(): array
    {
        return ['permission_codes' => ['present', 'array'], 'permission_codes.*' => ['required', 'string', 'distinct']];
    }
}
