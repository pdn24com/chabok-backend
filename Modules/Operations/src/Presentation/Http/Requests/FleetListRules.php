<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

final class FleetListRules
{
    public static function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'in:ACTIVE,INACTIVE'],
            'availability_status' => ['sometimes', 'nullable', 'in:AVAILABLE,ON_MISSION,TEMPORARILY_INACTIVE,MAINTENANCE,INACTIVE'],
            'home_node_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
