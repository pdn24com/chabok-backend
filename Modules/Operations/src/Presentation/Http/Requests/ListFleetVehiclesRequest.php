<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListFleetVehiclesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return FleetListRules::rules() + ['vehicle_type' => ['sometimes', 'nullable', 'in:MOTORCYCLE,CAR,VAN,LIGHT_TRUCK,TRUCK,TRAILER,OTHER']];
    }
}
