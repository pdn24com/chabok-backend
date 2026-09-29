<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;

final class ScheduleInputDocument
{
    public static function make(CommitmentScheduleDto $input): array
    {
        $windows = [];
        foreach ($input->windows as $window) {
            $windows[] = [
                'window_code' => $window->code, 'window_type' => $window->type->value, 'label_fa' => $window->label,
                'start_time' => $window->startTime, 'end_time' => $window->endTime, 'booking_cutoff_time' => $window->bookingCutoffTime,
                'applicable_weekdays' => $window->weekdays, 'risk_threshold_minutes' => $window->riskThresholdMinutes,
                'day_offset' => $window->dayOffset, 'active' => $window->active,
            ];
        }
        $scopes = [];
        foreach ($input->scopes as $scope) {
            $scopes[] = ['scope_type' => $scope->type->value, ...($scope->nodeId === null ? [] : ['node_id' => $scope->nodeId])];
        }
        $values = [
            'title' => $input->title, 'code' => $input->code, 'timezone' => $input->timezone, 'calendar_code' => $input->calendarCode,
            'valid_from' => $input->validFrom, 'valid_to' => $input->validTo, 'windows' => $windows, 'scopes' => $scopes,
            'commitment_policy' => $input->commitmentPolicy, 'expected_version' => $input->expectedVersion,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }
}
