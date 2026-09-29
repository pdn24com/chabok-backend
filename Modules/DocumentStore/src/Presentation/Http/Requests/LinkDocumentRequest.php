<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class LinkDocumentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resource_type' => ['required', Rule::enum(DocumentResourceType::class)],
            'resource_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            // What the document is there for on this record: «معرفی», «متن قرارداد» and the like.
            'purpose' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
