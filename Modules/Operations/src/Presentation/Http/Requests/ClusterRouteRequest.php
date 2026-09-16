<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class ClusterRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, ['expected_route_plan_version']);
    }

    public function rules(): array
    {
        return ['expected_route_plan_version' => ['required', 'integer', 'min:1']];
    }
}
