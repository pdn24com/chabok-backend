<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListManifestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(\Modules\Consignment\Application\OperationalStatusCatalog $statuses): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'state' => ['sometimes', 'array', 'max:50'],
            'state.*' => ['required', 'in:DRAFT,OPEN,CLOSED'],
            'manifest_status' => ['sometimes', 'array', 'max:50'],
            'manifest_status.*' => [
                'required',
                \Illuminate\Validation\Rule::in($statuses->codes($this->attributes->get('principal')->hqId, true)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        \Modules\Foundation\Presentation\Http\ListSelections::normalize($this, ['state', 'manifest_status']);
    }
}
