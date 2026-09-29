<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Mappers;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Modules\ServiceCatalog\Domain\Enums\CommitmentAnchor;
use Modules\ServiceCatalog\Domain\Enums\CommitmentCalculation;
use Modules\ServiceCatalog\Domain\Enums\CommitmentDurationUnit;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentContext;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentTimingPolicy;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindow;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;

final class CommitmentInput
{
    public static function timingPolicy(array $policy): CommitmentTimingPolicy
    {
        return new CommitmentTimingPolicy(
            mode: CommitmentMode::from($policy['mode']),
            anchor: CommitmentAnchor::from($policy['anchor'] ?? 'CONSIGNMENT_CREATED'),
            calculation: CommitmentCalculation::from($policy['calculation'] ?? 'ELAPSED'),
            durationValue: isset($policy['duration_value']) ? (int) $policy['duration_value'] : null,
            durationUnit: CommitmentDurationUnit::from($policy['duration_unit'] ?? 'HOUR'),
            dayOffset: (int) ($policy['day_offset'] ?? 0),
            windowCodes: array_values($policy['window_codes'] ?? []),
            riskThresholdMinutes: (int) ($policy['risk_threshold_minutes'] ?? 120),
        );
    }

    public static function context(OfferingSelectionContext $context, DateTimeInterface $defaultAcceptedAt): CommitmentContext
    {
        return new CommitmentContext(
            acceptedAt: isset($context->acceptanceAt) ? CarbonImmutable::parse($context->acceptanceAt) : CarbonImmutable::instance($defaultAcceptedAt),
            pickupCompletedAt: isset($context->pickupCompletedAt) ? CarbonImmutable::parse($context->pickupCompletedAt) : null,
            pickupStartsAt: isset($context->pickupStartsAt) ? CarbonImmutable::parse($context->pickupStartsAt) : null,
            pickupEndsAt: isset($context->pickupEndsAt) ? CarbonImmutable::parse($context->pickupEndsAt) : null,
            pickupServiceDate: ! empty($context->pickupServiceDate) ? $context->pickupServiceDate : null,
            pickupWindowCode: $context->pickupWindowCode ?? null,
            deliveryWindowCode: $context->deliveryWindowCode ?? null,
        );
    }

    public static function windowRecord(CommitmentScheduleWindowRecord $window): CommitmentWindow
    {
        return new CommitmentWindow($window->window_code, CommitmentWindowType::from($window->window_type), $window->label_fa,
            $window->start_time, $window->end_time, $window->booking_cutoff_time,
            array_map('intval', $window->applicable_weekdays), (int) ($window->risk_threshold_minutes ?? 120),
            (int) ($window->day_offset ?? 0), (bool) $window->active);
    }

    public static function window(array $input): CommitmentWindow
    {
        return new CommitmentWindow(
            code: $input['window_code'],
            type: CommitmentWindowType::from($input['window_type']),
            label: $input['label_fa'],
            startTime: $input['start_time'],
            endTime: $input['end_time'],
            bookingCutoffTime: $input['booking_cutoff_time'],
            weekdays: array_map('intval', array_values($input['applicable_weekdays'])),
            riskThresholdMinutes: (int) ($input['risk_threshold_minutes'] ?? 120),
            dayOffset: (int) ($input['day_offset'] ?? 0),
            active: (bool) ($input['active'] ?? true),
        );
    }
}
