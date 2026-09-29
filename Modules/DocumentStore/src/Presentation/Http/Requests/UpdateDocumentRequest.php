<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class UpdateDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A document always has a title and a classification, so neither can be sent as null.
            'title' => ['sometimes', 'string', 'max:200'],
            'classification' => ['sometimes', 'string', 'max:40'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'reference_no' => ['sometimes', 'nullable', 'string', 'max:120'],
            'expires_on' => ['sometimes', 'nullable', 'integer', 'min:-2208988800', 'max:4102444800'],
            // Retiring a document is its own action, so a routine edit cannot do it by accident.
            'status' => ['prohibited'],
            'links' => ['prohibited'],
        ];
    }
}
