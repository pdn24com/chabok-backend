<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class UpdateCatalogDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return CatalogRequestRules::draftRules((string) $this->route('resource'), false) + ['expected_version' => ['required', 'integer', 'min:1']];
    }
}
