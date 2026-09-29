<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesCommand;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesHandler;
use Modules\Foundation\Presentation\Http\ListSelections;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;

final class ListManifestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ListOperationalStatusCodesHandler $statuses): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'state' => ['sometimes', 'array', 'max:50'],
            'state.*' => ['required', 'in:DRAFT,OPEN,CLOSED'],
            'manifest_status' => ['sometimes', 'array', 'max:50'],
            'manifest_status.*' => ['required', Rule::in($statuses->handle(new ListOperationalStatusCodesCommand($this->attributes->get('principal')->hqId, true)))],
        ];
    }

    protected function prepareForValidation(): void
    {
        ListSelections::normalize($this, ['state', 'manifest_status']);
    }
}
