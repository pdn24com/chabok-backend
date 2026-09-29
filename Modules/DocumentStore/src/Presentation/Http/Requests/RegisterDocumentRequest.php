<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class RegisterDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            // Free text: the list of access classifications is still an open decision, so no enum here.
            'classification' => ['required', 'string', 'max:40'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'reference_no' => ['sometimes', 'nullable', 'string', 'max:120'],
            // A unix timestamp in seconds; only the calendar day of it is kept.
            'expires_on' => ['sometimes', 'nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            // A document may be registered attached to nothing and linked later.
            'links' => ['sometimes', 'array', 'max:50'],
            'links.*.resource_type' => ['required', Rule::enum(DocumentResourceType::class)],
            'links.*.resource_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'links.*.purpose' => ['sometimes', 'nullable', 'string', 'max:80'],
            // The archive decides the status: a document is registered active and retired by its own action.
            'status' => ['prohibited'],
        ];
    }
}
