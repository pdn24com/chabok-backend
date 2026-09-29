<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;
use Modules\ServiceCatalog\Application\Contracts\CommitmentClockInterface;
use Modules\ServiceCatalog\Domain\Enums\CommitmentCalculation;
use Modules\ServiceCatalog\Domain\Enums\CommitmentDurationUnit;
use Modules\ServiceCatalog\Domain\Enums\CommitmentFailure;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentRuleViolation;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentContext;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentResolution;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentTimingPolicy;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindow;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindowInstance;

/** Pure temporal rules; a holiday source can be supplied once its ownership is decided. */
final class CommitmentClock implements CommitmentClockInterface
{
    /** @param list<CommitmentWindow> $windows */
    public function resolve(
        CommitmentTimingPolicy $policy,
        array $windows,
        CommitmentContext $context,
        string $timezone,
        bool $includeHolidays,
        bool $requireSelection,
        CommitmentWindowType $kind,
        ?callable $isHoliday = null,
    ): CommitmentResolution {
        $mode = $policy->mode;
        if ($mode === CommitmentMode::None) {
            return new CommitmentResolution($policy);
        }
        $calculation = $policy->calculation;
        if ((! $includeHolidays || $calculation === CommitmentCalculation::BusinessDayEnd) && $isHoliday === null) {
            throw new CommitmentRuleViolation(CommitmentFailure::CalendarUnavailable);
        }
        $anchorValue = $context->anchor($policy->anchor);
        if ($anchorValue === null) {
            return new CommitmentResolution($policy, awaitingOperation: true);
        }
        $anchor = $anchorValue->setTimezone($timezone);
        if ($mode === CommitmentMode::Computed && $calculation === CommitmentCalculation::Elapsed) {
            return $this->elapsedCommitment($policy, $anchor, $includeHolidays, $isHoliday);
        }

        $date = $this->serviceDate($policy, $context, $anchor, $timezone, $includeHolidays, $kind, $isHoliday);
        if ($mode === CommitmentMode::Computed) {
            return new CommitmentResolution($policy, computedAt: $date->endOfDay()->utc());
        }
        $candidates = $this->candidateWindows($policy, $windows, $context, $anchor, $date, $timezone, $includeHolidays, $kind, $isHoliday);

        return $this->selectWindow($policy, $context, $candidates, $requireSelection, $kind);
    }

    private function elapsedCommitment(CommitmentTimingPolicy $policy, CarbonImmutable $anchor, bool $includeHolidays, ?callable $isHoliday): CommitmentResolution
    {
        if ($policy->durationValue === null || $policy->durationValue < 1) {
            throw new InvalidArgumentException('Elapsed commitments require a positive duration.');
        }
        $seconds = $policy->durationValue * match ($policy->durationUnit) {
            CommitmentDurationUnit::Minute => 60,
            CommitmentDurationUnit::Hour => 3600,
            CommitmentDurationUnit::Day => 86400,
        };
        $end = $anchor;
        $skipped = 0;
        if ($includeHolidays) {
            $end = $anchor->addSeconds($seconds);
            $seconds = 0;
        }
        while ($seconds > 0) {
            if (! $includeHolidays && $isHoliday($end)) {
                if (++$skipped > 36600) {
                    throw new LogicException('Calendar contains no operating day.');
                }
                $end = $end->addDay()->startOfDay();

                continue;
            }
            $next = $end->addDay()->startOfDay();
            $available = $end->diffInSeconds($next);
            $take = min($available, $seconds);
            $end = $end->addSeconds($take);
            $seconds -= $take;
        }

        return new CommitmentResolution($policy, computedAt: $end->utc());
    }

    private function serviceDate(CommitmentTimingPolicy $policy, CommitmentContext $context, CarbonImmutable $anchor, string $timezone, bool $includeHolidays, CommitmentWindowType $kind, ?callable $isHoliday): CarbonImmutable
    {
        $calculation = $policy->calculation;
        $date = $anchor->startOfDay();
        // Pickup windows are selected for an explicit requested local service date.
        if ($kind === CommitmentWindowType::Pickup && $context->pickupServiceDate !== null) {
            $date = CarbonImmutable::parse($context->pickupServiceDate, $timezone)->startOfDay();
        }
        $offset = $policy->dayOffset;
        for ($i = 0; $i < $offset; $i++) {
            $date = $date->addDay();
            if ($calculation === CommitmentCalculation::BusinessDayEnd || ! $includeHolidays) {
                $guard = 0;
                while ($isHoliday($date)) {
                    if (++$guard > 366) {
                        throw new LogicException('Calendar contains no operating day.');
                    }
                    $date = $date->addDay();
                }
            }
        }
        if ($offset === 0 && ($calculation === CommitmentCalculation::BusinessDayEnd || ! $includeHolidays)) {
            $guard = 0;
            while ($isHoliday($date)) {
                if (++$guard > 366) {
                    throw new LogicException('Calendar contains no operating day.');
                }
                $date = $date->addDay();
            }
        }

        return $date;
    }

    /** @param list<CommitmentWindow> $windows @return list<CommitmentWindowInstance> */
    private function candidateWindows(CommitmentTimingPolicy $policy, array $windows, CommitmentContext $context, CarbonImmutable $anchor, CarbonImmutable $date, string $timezone, bool $includeHolidays, CommitmentWindowType $kind, ?callable $isHoliday): array
    {
        $candidates = [];
        foreach ($windows as $window) {
            if ($window->type !== $kind || ! $window->active || $policy->windowCodes !== [] && ! in_array($window->code, $policy->windowCodes, true)) {
                continue;
            }
            if (! in_array($date->dayOfWeekIso, $window->weekdays, true)) {
                continue;
            }
            if (! $includeHolidays && $isHoliday($date)) {
                continue;
            }
            $start = CarbonImmutable::parse($date->toDateString().' '.$window->startTime, $timezone);
            $end = CarbonImmutable::parse($date->toDateString().' '.$window->endTime, $timezone);
            $cutoff = CarbonImmutable::parse($date->toDateString().' '.$window->bookingCutoffTime, $timezone);
            $booking = $context->acceptedAt;
            if ($booking->greaterThan($cutoff) || $end->lessThanOrEqualTo($anchor)) {
                continue;
            }
            $candidates[] = new CommitmentWindowInstance($window, $date->toDateString(), $start->utc(), $end->utc(), $cutoff->utc(), $timezone, $policy->dayOffset);
        }

        return $candidates;
    }

    /** @param list<CommitmentWindowInstance> $candidates */
    private function selectWindow(CommitmentTimingPolicy $policy, CommitmentContext $context, array $candidates, bool $requireSelection, CommitmentWindowType $kind): CommitmentResolution
    {
        $code = $context->selectedWindow($kind);
        $selected = null;
        foreach ($candidates as $candidate) {
            if ($candidate->window->code === $code) {
                $selected = $candidate;
            }
        }
        // An explicit single destination window is itself the promise, not another required selection.
        if ($selected === null && ! $code && count($candidates) === 1 && $kind === CommitmentWindowType::Delivery) {
            $selected = $candidates[0];
        }
        if ($code && ! $selected) {
            throw new CommitmentRuleViolation($kind === CommitmentWindowType::Pickup ? CommitmentFailure::PickupWindowInvalid : CommitmentFailure::DeliveryWindowInvalid);
        }
        if ($requireSelection && ! $selected) {
            throw new CommitmentRuleViolation($kind === CommitmentWindowType::Pickup ? CommitmentFailure::PickupWindowRequired : CommitmentFailure::DeliveryWindowRequired);
        }

        return new CommitmentResolution($policy, windows: $candidates, selected: $selected);
    }
}
