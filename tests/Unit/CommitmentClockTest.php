<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Application\CommitmentClock;
use Modules\Foundation\Domain\ApiException;
use PHPUnit\Framework\TestCase;

final class CommitmentClockTest extends TestCase
{
    private function policy(array $patch = []): array
    {
        return [
            ...[
                'mode' => 'COMPUTED',
                'anchor' => 'CONSIGNMENT_CREATED',
                'calculation' => 'ELAPSED',
                'duration_value' => 24,
                'duration_unit' => 'HOUR',
                'day_offset' => 1,
                'window_codes' => [],
            ],
            ...$patch,
        ];
    }

    public function test_no_commitment_modes_do_not_require_windows_or_a_pickup_anchor(): void
    {
        \Illuminate\Support\Facades\Validator::swap(new \Illuminate\Validation\Factory(new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en')));
        try {
            $zones = $this->createStub(\Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver::class);
            $policy = \Modules\ServiceCatalog\Application\SchedulePolicy::fromBinding(['pickup_mode' => 'NONE', 'delivery_mode' => 'NONE']);
            self::assertSame($policy, (new \Modules\ServiceCatalog\Application\SchedulePolicy($zones, new \Modules\ServiceCatalog\Infrastructure\Adapters\LaravelScheduleInputValidator()))->validate($policy, [], 'tenant'));
        } finally {
            \Illuminate\Support\Facades\Validator::clearResolvedInstance('validator');
        }
    }

    public function test_elapsed_hours_and_end_of_next_day_are_different(): void
    {
        $clock = new CommitmentClock();
        $context = ['acceptance_at' => '2026-09-14T10:00:00+03:30'];
        $elapsed = $clock->resolve($this->policy(), [], $context, 'Asia/Tehran', true, true, 'DELIVERY');
        $day = $clock->resolve($this->policy(['calculation' => 'DAY_END']), [], $context, 'Asia/Tehran', true, true, 'DELIVERY');
        self::assertSame('2026-09-15T06:30:00.000000Z', $elapsed['computed_at']);
        self::assertSame(120, $elapsed['risk_threshold_minutes']);
        $fractional = $clock->resolve($this->policy(), [], ['acceptance_at' => '2026-09-14T23:59:59.123456Z'], 'UTC', true, true, 'DELIVERY');
        self::assertSame('2026-09-15T23:59:59.123456Z', $fractional['computed_at']);
        self::assertSame('2026-09-15T20:29:59.999999Z', $day['computed_at']);
    }

    public function test_holiday_source_is_required_and_a_supplied_source_skips_days(): void
    {
        $clock = new CommitmentClock();
        $context = ['acceptance_at' => '2026-09-14T10:00:00Z'];
        try {
            $clock->resolve($this->policy(), [], $context, 'UTC', false, true, 'DELIVERY');
            self::fail();
        } catch (ApiException $e) {
            self::assertSame('SLA_CALENDAR_UNAVAILABLE', $e->details['reason_code']);
        }
        $holiday = fn(CarbonImmutable $day) => $day->toDateString() === '2026-09-15';
        $result = $clock->resolve($this->policy(), [], $context, 'UTC', false, true, 'DELIVERY', $holiday);
        self::assertSame('2026-09-16T10:00:00.000000Z', $result['computed_at']);
        $result = $clock->resolve($this->policy(['calculation' => 'BUSINESS_DAY_END']), [], $context, 'UTC', true, true, 'DELIVERY', $holiday);
        self::assertSame('2026-09-16T23:59:59.999999Z', $result['computed_at']);
    }

    public function test_same_day_window_cutoff_and_actual_pickup_anchor(): void
    {
        $clock = new CommitmentClock();
        $policy = $this->policy(['mode' => 'SELECTABLE_WINDOW', 'day_offset' => 0]);
        $window = [
            'risk_threshold_minutes' => 45,
            'window_type' => 'DELIVERY',
            'window_code' => 'EVENING',
            'label_fa' => 'عصر',
            'active' => true,
            'start_time' => '16:00:12',
            'end_time' => '20:00:34',
            'booking_cutoff_time' => '15:00:56',
            'applicable_weekdays' => [1],
        ];
        $result = $clock->resolve($policy, [$window], ['acceptance_at' => '2026-09-14T09:00:00Z'], 'UTC', true, true, 'DELIVERY');
        self::assertSame('2026-09-14T16:00:12.000000Z', $result['starts_at']);
        self::assertSame(45, $result['risk_threshold_minutes']);
        $window['risk_threshold_minutes'] = 90;
        self::assertSame(45, $result['selected']['risk_threshold_minutes']);
        self::assertSame('2026-09-14T20:00:34.000000Z', $result['ends_at']);
        try {
            $clock->resolve($policy, [$window], ['acceptance_at' => '2026-09-14T15:01:00Z', 'delivery_window_code' => 'EVENING'], 'UTC', true, true, 'DELIVERY');
            self::fail();
        } catch (ApiException $e) {
            self::assertSame('DELIVERY_WINDOW_INVALID', $e->details['reason_code']);
        }
        $pending = $clock->resolve($this->policy(['anchor' => 'PICKUP_COMPLETED']), [], [], 'UTC', true, true, 'DELIVERY');
        self::assertTrue($pending['awaiting_operation']);
        $done = $clock->resolve($this->policy(['anchor' => 'PICKUP_COMPLETED']), [], ['pickup_completed_at' => '2026-09-14T12:00:00Z'], 'UTC', true, true, 'DELIVERY');
        self::assertSame('2026-09-15T12:00:00.000000Z', $done['computed_at']);
    }
}
