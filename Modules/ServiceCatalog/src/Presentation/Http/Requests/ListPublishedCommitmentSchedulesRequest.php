<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListPublishedCommitmentSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['include_version_ids' => ['nullable', 'array', 'max:100'], 'include_version_ids.*' => ['uuid']];
    }
}
