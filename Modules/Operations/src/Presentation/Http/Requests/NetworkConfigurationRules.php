<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Requests;

use Illuminate\Validation\Rule;

final class NetworkConfigurationRules
{
    public static function coverageVersionRules(bool $update): array
    {
        return [
            'source_version_id' => [$update ? 'prohibited' : 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'expected_version' => [$update ? 'required' : 'prohibited', 'integer', 'min:1'],
            'rules' => [$update ? 'sometimes' : 'required', 'array', 'min:1'],
            'rules.*.target' => ['required', Rule::in(['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE'])],
            'rules.*.target_node_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.priority' => ['required', 'integer', 'between:-100000,100000'],
            'rules.*.offering_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.criterion' => ['required', 'array'],
            'rules.*.criterion.criterion_type' => ['required', Rule::in(['PROVINCE', 'CITY', 'POSTAL_RANGE', 'POLYGON', 'POINT_RADIUS'])],
            'rules.*.criterion.province_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.criterion.city_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.criterion.postal_code_from' => ['nullable', 'regex:/^\d{10}$/'],
            'rules.*.criterion.postal_code_to' => ['nullable', 'regex:/^\d{10}$/'],
            'rules.*.criterion.geometry' => ['nullable', 'array'],
            'rules.*.criterion.center' => ['nullable', 'array'],
            'rules.*.criterion.center.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'rules.*.criterion.center.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rules.*.criterion.radius_meters' => ['nullable', 'integer', 'between:1,500000'],
        ];
    }

    /** @return array<string,list<mixed>> */
    public static function routeVersionRules(bool $update): array
    {
        return [
            'source_version_id' => [$update ? 'prohibited' : 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'purpose' => [$update ? 'prohibited' : 'required', Rule::in(['TRUNK', 'LAST_MILE'])],
            'origin_node_id' => [$update ? 'prohibited' : 'required', 'integer', 'min:1', 'max:4294967295'],
            'destination_node_id' => [$update ? 'prohibited' : 'required', 'integer', 'min:1', 'max:4294967295'],
            'priority' => [$update ? 'sometimes' : 'required', 'integer', 'between:-100000,100000'],
            'offering_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'expected_version' => [$update ? 'required' : 'prohibited', 'integer', 'min:1'],
            'legs' => [$update ? 'sometimes' : 'required', 'array', 'min:1'],
            'legs.*.leg_order' => ['required', 'integer', 'min:1'],
            'legs.*.origin_node_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'legs.*.destination_node_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ];
    }

    /** @return array<string,list<mixed>> */
    public static function lifecycleRules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'string', 'max:500']];
    }
}
