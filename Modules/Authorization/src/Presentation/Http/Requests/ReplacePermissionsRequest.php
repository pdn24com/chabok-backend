<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ReplacePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['permission_codes' => ['present', 'array'], 'permission_codes.*' => ['required', 'string', 'distinct']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['permission_codes']);
    }
}
