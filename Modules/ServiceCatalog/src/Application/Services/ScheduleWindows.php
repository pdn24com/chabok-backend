<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleWindowsInterface;
use Modules\ServiceCatalog\Application\Mappers\CommitmentInput;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowUnavailability;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentWindowUnavailable;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindowInstance;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;

final readonly class ScheduleWindows implements ScheduleWindowsInterface
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    /** @param Collection<int, CommitmentScheduleWindowRecord> $windows @return list<CommitmentWindowInstance> */
    public function pickupWindowOptions(
        Collection $windows,
        string $timezone,
        ?string $serviceDate,
    ): array {
        $now = CarbonImmutable::instance($this->clock->now())->setTimezone($timezone);
        $windows = $windows->where('window_type', CommitmentWindowType::Pickup->value)->filter(fn ($window) => (bool) $window->active)->sortBy('start_time');
        $options = [];
        foreach ($windows as $window) {
            // A preview omits windows that are unavailable for the selected date.
            $instance = $serviceDate === null
                ? $this->nextWindow($window, $timezone, $now)
                : $this->inspectWindow($window, CommitmentWindowType::Pickup, $serviceDate, $timezone);
            if ($instance instanceof CommitmentWindowInstance) {
                $options[] = $instance;
            }
        }

        return $options;
    }

    public function nextWindow(
        CommitmentScheduleWindowRecord $window,
        string $timezone,
        CarbonImmutable $now,
    ): ?CommitmentWindowInstance {
        $localNow = $now->setTimezone($timezone);
        $days = $window->applicable_weekdays;
        for ($offset = 0; $offset < 14; $offset++) {
            $date = $localNow->startOfDay()->addDays($offset + (int) ($window->day_offset ?? 0));
            if (! in_array($date->dayOfWeekIso, array_map('intval', $days), true)) {
                continue;
            }
            $cutoff = CarbonImmutable::parse($date->toDateString().' '.$window->booking_cutoff_time, $timezone);
            if ($localNow->greaterThan($cutoff)) {
                continue;
            }

            return $this->createInstance($window, $date, $timezone, $cutoff);
        }

        return null;
    }

    public function windowInstance(
        ?CommitmentScheduleWindowRecord $window,
        CommitmentWindowType $windowType,
        string $serviceDate,
        string $timezone,
    ): CommitmentWindowInstance {
        $resolved = $this->inspectWindow($window, $windowType, $serviceDate, $timezone);
        if ($resolved instanceof CommitmentWindowUnavailability) {
            throw new CommitmentWindowUnavailable($resolved, $windowType);
        }

        return $resolved;
    }

    public function inspectWindow(
        ?CommitmentScheduleWindowRecord $window,
        CommitmentWindowType $windowType,
        string $serviceDate,
        string $timezone,
    ): CommitmentWindowInstance|CommitmentWindowUnavailability {
        if ($window === null || $window->window_type !== $windowType->value || ! $window->active) {
            return CommitmentWindowUnavailability::WindowInvalid;
        }
        $date = CarbonImmutable::parse($serviceDate, $timezone)->startOfDay()->addDays((int) ($window->day_offset ?? 0));
        if (! in_array($date->dayOfWeekIso, array_map('intval', (array) $window->applicable_weekdays), true)) {
            return CommitmentWindowUnavailability::DateInvalid;
        }
        $cutoff = CarbonImmutable::parse($date->toDateString().' '.$window->booking_cutoff_time, $timezone);
        if ($windowType === CommitmentWindowType::Pickup && CarbonImmutable::instance($this->clock->now())->setTimezone($timezone)->greaterThan($cutoff)) {
            return CommitmentWindowUnavailability::CutoffPassed;
        }

        return $this->createInstance($window, $date, $timezone, $cutoff);
    }

    private function createInstance(
        CommitmentScheduleWindowRecord $window,
        CarbonImmutable $date,
        string $timezone,
        CarbonImmutable $cutoff,
    ): CommitmentWindowInstance {
        return new CommitmentWindowInstance(CommitmentInput::windowRecord($window), $date->toDateString(),
            CarbonImmutable::parse($date->toDateString().' '.$window->start_time, $timezone)->utc(),
            CarbonImmutable::parse($date->toDateString().' '.$window->end_time, $timezone)->utc(),
            $cutoff->utc(), $timezone, (int) $window->day_offset);
    }
}
