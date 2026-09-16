<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListAvailableDriversRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['capability' => ['sometimes', 'nullable', 'in:PICKUP,LINEHAUL,DELIVERY']];
    }
}
