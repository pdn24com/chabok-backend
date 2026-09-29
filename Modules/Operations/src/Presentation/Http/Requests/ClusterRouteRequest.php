<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest as FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ClusterRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['expected_route_plan_version' => ['required', 'integer', 'min:1']];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_route_plan_version']);
    }
}
