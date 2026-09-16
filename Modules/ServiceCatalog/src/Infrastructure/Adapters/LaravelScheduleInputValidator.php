<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Adapters;

use Illuminate\Support\Facades\Validator;
use Modules\ServiceCatalog\Application\Contracts\ScheduleInputValidator;

final class LaravelScheduleInputValidator implements ScheduleInputValidator
{
    public function validate(array $policy): void
    {
        $rules = [
            'include_holidays' => 'required|boolean',
            'zone_set_id' => 'nullable|uuid',
            'pickup' => 'required|array',
            'delivery' => 'present|nullable|array|min:1',
            'destination_rules' => 'present|array|max:200',
            'destination_rules.*.id' => 'required|uuid|distinct',
            'destination_rules.*.destination_zone_code' => 'required|string|max:80|distinct',
            'destination_rules.*.origin_zone_code' => 'nullable|prohibited',
            'destination_rules.*.policy' => 'required|array',
        ];
        foreach (['pickup', ...!empty($policy['delivery']) ? ['delivery'] : [], 'destination_rules.*.policy'] as $prefix) {
            foreach ([
                'risk_threshold_minutes' => 'sometimes|integer|min:0|max:525600',
                'mode' => 'required|in:NONE,SELECTABLE_WINDOW,COMPUTED',
                'anchor' => 'required|in:CONSIGNMENT_CREATED,PICKUP_COMPLETED,PICKUP_COMMITMENT_START,PICKUP_COMMITMENT_END',
                'calculation' => 'required|in:ELAPSED,DAY_END,BUSINESS_DAY_END',
                'duration_value' => 'required|integer|min:1|max:8760',
                'duration_unit' => 'required|in:MINUTE,HOUR,DAY',
                'day_offset' => 'required|integer|min:0|max:365',
                'window_codes' => 'present|array',
                'window_codes.*' => 'string|distinct',
            ] as $key => $rule) {
                $rules[$prefix . '.' . $key] = $rule;
            }
        }
        Validator::make($policy, $rules)->validate();
    }
}
