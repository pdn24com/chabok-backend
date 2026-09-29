<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class ListSalesFunnelsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A tenant keeps a handful of funnels, so the whole set is returned and there is no page to ask for.
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
