<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListOpportunityBoardRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The funnel decides the columns, so a board cannot be drawn without one.
            'funnel_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
        ];
    }
}
