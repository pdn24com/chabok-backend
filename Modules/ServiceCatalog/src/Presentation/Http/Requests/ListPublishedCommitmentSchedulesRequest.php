<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ListPublishedCommitmentSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['include_version_ids' => ['nullable', 'array', 'max:100'], 'include_version_ids.*' => ['integer', 'min:1', 'max:4294967295']];
    }
}
