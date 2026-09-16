<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

final class CatalogRequestRules
{
    public static function draftRules(string $resource, bool $creating): array
    {
        $rules = [
            'labels' => ['required', 'array'],
            'description' => ['nullable', 'string', 'max:4000'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
        ];
        if ($creating) {
            $rules['code'] = ['sometimes', 'nullable', 'regex:/^(?:[0-9]{6}|[A-Z][A-Z0-9_]{1,79})$/'];
        }
        if ($resource !== 'offerings') {
            return $rules + ['definition' => ['sometimes', 'array']];
        }
        return $rules + [
            'service_type_version_id' => ['required', 'uuid'],
            'shipping_method_version_id' => ['required', 'uuid'],
            'sla_policy' => ['required_without:commitment_binding', 'array'],
            'availability_summary' => ['nullable', 'array'],
            'option_rules' => ['array'],
            'eligibility_rules' => ['array'],
            'coverage_references' => ['array'],
            'availability_bindings' => ['required', 'array', 'min:1'],
            'option_rules.*.service_option_version_id' => ['required', 'uuid'],
            'option_rules.*.compatibility' => ['required', 'in:ALLOWED,REQUIRED,FORBIDDEN,CONDITIONAL'],
            'option_rules.*.condition' => ['nullable', 'array'],
            'eligibility_rules.*.dimension' => ['required', 'in:GEOGRAPHY,PHYSICAL,CONTENT,VALUE,COMMERCIAL,OPERATIONAL,TEMPORAL,OPTION,CHANNEL'],
            'eligibility_rules.*.fact_key' => ['required', 'string', 'max:120'],
            'eligibility_rules.*.operator' => ['required', 'in:EQ,NEQ,IN,NOT_IN,MIN,MAX,BETWEEN,EXISTS,NOT_EXISTS'],
            'eligibility_rules.*.expected_value' => ['present'],
            'eligibility_rules.*.reason_code' => ['required', 'string', 'max:120'],
            'eligibility_rules.*.priority' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'coverage_references.*.direction' => ['required', 'in:ORIGIN,DESTINATION,LANE,BOTH'],
            'coverage_references.*.reference_type' => ['required', 'in:COUNTRY,PROVINCE,CITY,POSTAL_RANGE,OPERATIONAL_AREA,PRICING_ZONE_SET'],
            'coverage_references.*.reference_value' => ['required', 'string', 'max:200'],
            'coverage_references.*.secondary_reference_value' => ['nullable', 'string', 'max:200'],
            'coverage_references.*.priority' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'availability_bindings.*.scope_type' => ['required', 'in:PLATFORM,TENANT,CUSTOMER_SEGMENT,CUSTOMER,CONTRACT,CHANNEL'],
            'availability_bindings.*.scope_value' => ['nullable', 'string', 'max:120'],
            'availability_bindings.*.enabled' => ['sometimes', 'boolean'],
            'commitment_binding' => ['nullable', 'array'],
            'commitment_binding.commitment_schedule_version_id' => ['required_with:commitment_binding', 'uuid'],
            'commitment_binding.pickup_mode' => ['required_with:commitment_binding', 'in:NONE,SELECTABLE_WINDOW,COMPUTED'],
            'commitment_binding.delivery_mode' => ['required_with:commitment_binding', 'in:NONE,SELECTABLE_WINDOW,COMPUTED'],
            'commitment_binding.duration_value' => ['required_if:commitment_binding.delivery_mode,COMPUTED', 'nullable', 'integer', 'min:1'],
            'commitment_binding.duration_unit' => ['required_if:commitment_binding.delivery_mode,COMPUTED', 'nullable', 'in:MINUTE,HOUR,DAY'],
            'commitment_binding.duration_anchor' => [
                'required_if:commitment_binding.delivery_mode,COMPUTED',
                'nullable',
                'in:CONSIGNMENT_CREATED,PICKUP_COMMITMENT_START,PICKUP_COMMITMENT_END,PICKUP_COMPLETED',
            ],
        ];
    }
    /** @return array<string,mixed> */

    public static function scheduleRules(bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'timezone' => ['sometimes', 'timezone'],
            'calendar_code' => ['sometimes', 'string', 'max:80'],
            'commitment_policy' => ['sometimes', 'array'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after:valid_from'],
            'windows' => ['present', 'array'],
            'windows.*.window_code' => ['required', 'distinct', 'regex:/^[A-Z][A-Z0-9_]{1,79}$/'],
            'windows.*.window_type' => ['required', 'in:PICKUP,DELIVERY'],
            'windows.*.risk_threshold_minutes' => ['sometimes', 'integer', 'min:0', 'max:525600'],
            'windows.*.label_fa' => ['required', 'string', 'max:200'],
            'windows.*.start_time' => ['required', 'date_format:H:i,H:i:s'],
            'windows.*.end_time' => ['required', 'date_format:H:i,H:i:s'],
            'windows.*.booking_cutoff_time' => ['required', 'date_format:H:i,H:i:s'],
            'windows.*.applicable_weekdays' => ['required', 'array', 'min:1'],
            'windows.*.applicable_weekdays.*' => ['integer', 'between:1,7'],
            'windows.*.day_offset' => ['sometimes', 'integer', 'between:0,30'],
            'windows.*.active' => ['sometimes', 'boolean'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*.scope_type' => ['required', 'in:HQ,NODE'],
            'scopes.*.node_id' => ['required_if:scopes.*.scope_type,NODE', 'nullable', 'uuid'],
        ];
        if ($creating) {
            $rules['code'] = ['sometimes', 'nullable', 'regex:/^(?:[0-9]{6}|[A-Z][A-Z0-9_]{1,79})$/'];
        }
        return $rules;
    }
}
