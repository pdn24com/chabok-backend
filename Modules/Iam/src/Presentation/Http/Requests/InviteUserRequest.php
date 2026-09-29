<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['channel' => ['required', 'in:SMS,EMAIL']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['channel']);
    }
}
