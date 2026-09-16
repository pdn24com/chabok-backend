<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCatalogIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return CatalogRequestRules::draftRules((string) $this->route('resource'), true);
    }
}
