<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class AddManifestParcelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'input_source' => ['required', 'in:SCAN,MANUAL,BATCH,AWAITING'],
            'identifiers' => ['required', 'array', 'min:1', 'max:200'],
            'identifiers.*' => ['required', 'string', 'max:64', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_version', 'input_source', 'identifiers']);
    }
}
