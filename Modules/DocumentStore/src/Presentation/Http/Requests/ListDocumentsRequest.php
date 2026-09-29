<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListDocumentsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', Rule::enum(DocumentStatus::class)],
            'category_id' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
            'q' => ['sometimes', 'string', 'max:200'],
            // The pair narrows the archive to one record, which is the documents tab of that record;
            // half a pair would narrow to nothing usable, so the two are demanded together.
            'resource_type' => ['sometimes', Rule::enum(DocumentResourceType::class), 'required_with:resource_id'],
            'resource_id' => ['sometimes', 'integer', 'min:1', 'max:4294967295', 'required_with:resource_type'],
        ];
    }
}
