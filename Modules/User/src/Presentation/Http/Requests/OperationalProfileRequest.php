<?php

declare(strict_types=1);

namespace Modules\User\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class OperationalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['operational_profile']);
    }

    public function rules(): array
    {
        return ['operational_profile' => ['required', 'array:kind,mode,existing_id,expected_version,role_id,driver,node']] + OperationalProfileRules::rules((array) $this->input('operational_profile', []));
    }
}
