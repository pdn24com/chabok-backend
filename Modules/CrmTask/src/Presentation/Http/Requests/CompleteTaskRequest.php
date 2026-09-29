<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class CompleteTaskRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Work is never finished silently: what came of it is recorded with it.
        return ['completion_result' => ['required', 'string', 'max:5000']];
    }
}
