<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\CrmTask\Domain\Enums\TaskBucket;
use Modules\CrmTask\Domain\Enums\TaskScope;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListTasksRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::enum(TaskScope::class)],
            'bucket' => ['sometimes', 'nullable', Rule::enum(TaskBucket::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
