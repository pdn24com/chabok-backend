<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CoverageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['policy_code' => ['required', 'string', 'max:80'], 'policy_title' => ['required', 'string', 'max:200']];
    }
}
