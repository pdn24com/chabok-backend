<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RouteVersionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['page' => ['integer', 'min:1'], 'per_page' => ['integer', 'min:1', 'max:100']];
    }
}
