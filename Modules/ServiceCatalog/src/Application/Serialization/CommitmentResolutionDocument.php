<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Domain\Enums\CommitmentCalculation;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentResolution;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindowInstance;

final class CommitmentResolutionDocument
{
    public static function serialize(CommitmentResolution $resolution, array $policySnapshot): array
    {
        $policy = $resolution->policy;
        if ($policy->mode === CommitmentMode::None) {
            return ['mode' => 'NONE'];
        }
        $result = ['risk_threshold_minutes' => $policy->riskThresholdMinutes, 'mode' => $policy->mode->value, 'anchor' => $policy->anchor->value];
        if ($resolution->awaitingOperation) {
            return [...$result, 'awaiting_operation' => true, 'policy' => $policySnapshot, 'computed_at' => null];
        }
        if ($policy->mode === CommitmentMode::Computed) {
            $calculation = $policy->calculation === CommitmentCalculation::Elapsed
                ? ['duration_value' => $policy->durationValue, 'duration_unit' => $policy->durationUnit->value]
                : ['calculation' => $policy->calculation->value];

            return [...$result, ...$calculation, 'computed_at' => $resolution->computedAt?->utc()->toISOString(), 'awaiting_operation' => false];
        }
        $selected = $resolution->selected === null ? null : self::window($resolution->selected);

        return [...$result, 'windows' => array_map(self::window(...), $resolution->windows), 'selected' => $selected, ...$selected ?? []];
    }

    public static function window(CommitmentWindowInstance $instance): array
    {
        $window = $instance->window;

        return [
            'risk_threshold_minutes' => $window->riskThresholdMinutes,
            'window_code' => $window->code, 'window_type' => $window->type->value, 'label_fa' => $window->label,
            'service_date' => $instance->serviceDate, 'starts_at' => $instance->startsAt->utc()->toISOString(),
            'ends_at' => $instance->endsAt->utc()->toISOString(), 'booking_cutoff_at' => $instance->bookingCutoffAt->utc()->toISOString(),
            'timezone' => $instance->timezone, 'day_offset' => $instance->dayOffset,
        ];
    }
}
