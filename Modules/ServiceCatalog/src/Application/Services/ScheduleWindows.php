<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ScheduleWindows
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
    )
    {
    }

    public function pickupWindowOptions(string $versionId, string $timezone, ?string $serviceDate): array
    {
        $now = CarbonImmutable::instance($this->clock->now())->setTimezone($timezone);
        $windows = $this->schedules->pickupWindows($versionId);
        $options = [];
        foreach ($windows as $window) {
            if ($serviceDate === null) {
                $instance = $this->nextWindow((array) $window, $timezone, $now);
                if ($instance !== null) {
                    $options[] = $instance;
                }
                continue;
            }
            try {
                $options[] = $this->windowInstance($versionId, 'PICKUP', (string) $window->window_code, $serviceDate, $timezone);
            } catch (ApiException) {
                // A preview omits windows that are unavailable for the selected date.
            }
        }
        return $options;
    }

    public function nextWindow(array $window, string $timezone, CarbonImmutable $now): ?array
    {
        $localNow = $now->setTimezone($timezone);
        $days = (array) $this->scheduleReader->decodeWindow($window)['applicable_weekdays'];
        for ($offset = 0; $offset < 14; $offset++) {
            $date = $localNow->startOfDay()->addDays($offset + (int) ($window['day_offset'] ?? 0));
            if (!in_array($date->dayOfWeekIso, array_map('intval', $days), true)) {
                continue;
            }
            $cutoff = CarbonImmutable::parse($date->toDateString() . ' ' . $window['booking_cutoff_time'], $timezone);
            if ($localNow->greaterThan($cutoff)) {
                continue;
            }
            return $this->instancePayload($window, $date, $timezone, $cutoff);
        }
        return null;
    }

    public function windowInstance(string $versionId, string $type, string $code, string $serviceDate, string $timezone): array
    {
        $window = $this->schedules->activeWindow($versionId, $type, $code);
        if ($window === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The commitment window is not valid for this Offering.', details: ['reason_code' => "{$type}_WINDOW_INVALID"]);
        }
        $row = $this->scheduleReader->decodeWindow((array) $window);
        $date = CarbonImmutable::parse($serviceDate, $timezone)->startOfDay()->addDays((int) ($row['day_offset'] ?? 0));
        if (!in_array($date->dayOfWeekIso, array_map('intval', (array) $row['applicable_weekdays']), true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The commitment window is not available on that date.', details: ['reason_code' => "{$type}_WINDOW_DATE_INVALID"]);
        }
        $cutoff = CarbonImmutable::parse($date->toDateString() . ' ' . $row['booking_cutoff_time'], $timezone);
        if ($type === 'PICKUP' && CarbonImmutable::instance($this->clock->now())->setTimezone($timezone)->greaterThan($cutoff)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Pickup booking cut-off has passed.', details: ['reason_code' => 'PICKUP_CUTOFF_PASSED']);
        }
        return $this->instancePayload($row, $date, $timezone, $cutoff);
    }

    public function instancePayload(array $window, CarbonImmutable $date, string $timezone, CarbonImmutable $cutoff): array
    {
        return [
            'risk_threshold_minutes' => (int) ($window['risk_threshold_minutes'] ?? 120),
            'window_code' => (string) $window['window_code'],
            'window_type' => (string) $window['window_type'],
            'label_fa' => (string) $window['label_fa'],
            'service_date' => $date->toDateString(),
            'starts_at' => CarbonImmutable::parse($date->toDateString() . ' ' . $window['start_time'], $timezone)->utc()->toISOString(),
            'ends_at' => CarbonImmutable::parse($date->toDateString() . ' ' . $window['end_time'], $timezone)->utc()->toISOString(),
            'booking_cutoff_at' => $cutoff->utc()->toISOString(),
            'timezone' => $timezone,
            'day_offset' => (int) $window['day_offset'],
        ];
    }
}
