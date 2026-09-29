<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Mappers\CommitmentInput;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Application\Serialization\CommitmentResolutionDocument;
use Modules\ServiceCatalog\Application\Services\CommitmentClock;
use Modules\ServiceCatalog\Application\Services\SchedulePolicy;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentRuleViolation;
use Modules\ServiceCatalog\Infrastructure\Adapters\LaravelScheduleInputValidator;
use PHPUnit\Framework\TestCase;

final class CommitmentClockTest extends TestCase
{
    public function test_no_commitment_modes_do_not_require_windows_or_a_pickup_anchor(): void
    {
        Validator::swap(new Factory(new Translator(new ArrayLoader, 'en')));
        try {
            $zones = $this->createStub(CommitmentZoneResolverInterface::class);
            $policy = SchedulePolicy::fromBinding(['pickup_mode' => 'NONE', 'delivery_mode' => 'NONE']);
            self::assertSame($policy, (new SchedulePolicy($zones, new LaravelScheduleInputValidator))->validate($policy, [], '245213294'));
        } finally {
            Validator::clearResolvedInstance('validator');
        }
    }

    public function test_elapsed_hours_and_end_of_next_day_are_different(): void
    {
        $context = ['acceptance_at' => '2026-09-14T10:00:00+03:30'];
        $elapsed = $this->resolve($this->policy(), [], $context, 'Asia/Tehran', true, true, 'DELIVERY');
        $day = $this->resolve($this->policy(['calculation' => 'DAY_END']), [], $context, 'Asia/Tehran', true, true, 'DELIVERY');
        self::assertSame('2026-09-15T06:30:00.000000Z', $elapsed['computed_at']);
        self::assertSame(120, $elapsed['risk_threshold_minutes']);
        $fractional = $this->resolve($this->policy(), [], ['acceptance_at' => '2026-09-14T23:59:59.123456Z'], 'UTC', true, true, 'DELIVERY');
        self::assertSame('2026-09-15T23:59:59.123456Z', $fractional['computed_at']);
        self::assertSame('2026-09-15T20:29:59.999999Z', $day['computed_at']);
    }

    public function test_holiday_source_is_required_and_a_supplied_source_skips_days(): void
    {
        $context = ['acceptance_at' => '2026-09-14T10:00:00Z'];
        try {
            $this->resolve($this->policy(), [], $context, 'UTC', false, true, 'DELIVERY');
            self::fail();
        } catch (CommitmentRuleViolation $e) {
            self::assertSame('SLA_CALENDAR_UNAVAILABLE', $e->reason->value);
        }
        $holiday = fn (CarbonImmutable $day) => $day->toDateString() === '2026-09-15';
        $result = $this->resolve($this->policy(), [], $context, 'UTC', false, true, 'DELIVERY', $holiday);
        self::assertSame('2026-09-16T10:00:00.000000Z', $result['computed_at']);
        $result = $this->resolve($this->policy(['calculation' => 'BUSINESS_DAY_END']), [], $context, 'UTC', true, true, 'DELIVERY', $holiday);
        self::assertSame('2026-09-16T23:59:59.999999Z', $result['computed_at']);
    }

    public function test_same_day_window_cutoff_and_actual_pickup_anchor(): void
    {
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
        $result = $this->resolve($policy, [$window], ['acceptance_at' => '2026-09-14T09:00:00Z'], 'UTC', true, true, 'DELIVERY');
        self::assertSame('2026-09-14T16:00:12.000000Z', $result['starts_at']);
        self::assertSame(45, $result['risk_threshold_minutes']);
        $window['risk_threshold_minutes'] = 90;
        self::assertSame(45, $result['selected']['risk_threshold_minutes']);
        self::assertSame('2026-09-14T20:00:34.000000Z', $result['ends_at']);
        try {
            $this->resolve($policy, [$window], ['acceptance_at' => '2026-09-14T15:01:00Z', 'delivery_window_code' => 'EVENING'], 'UTC', true, true, 'DELIVERY');
            self::fail();
        } catch (CommitmentRuleViolation $e) {
            self::assertSame('DELIVERY_WINDOW_INVALID', $e->reason->value);
        }
        $pending = $this->resolve($this->policy(['anchor' => 'PICKUP_COMPLETED']), [], [], 'UTC', true, true, 'DELIVERY');
        self::assertTrue($pending['awaiting_operation']);
        $done = $this->resolve($this->policy(['anchor' => 'PICKUP_COMPLETED']), [], ['pickup_completed_at' => '2026-09-14T12:00:00Z'], 'UTC', true, true, 'DELIVERY');
        self::assertSame('2026-09-15T12:00:00.000000Z', $done['computed_at']);
    }

    public function test_elapsed_time_keeps_actual_hours_across_dst_and_has_no_implicit_system_clock(): void
    {
        $policy = CommitmentInput::timingPolicy($this->policy());
        $context = CommitmentInput::context(OfferingSelectionInput::fromArray([]), CarbonImmutable::parse('2026-03-07T12:00:00-05:00'));
        $resolution = (new CommitmentClock)->resolve($policy, [], $context, 'America/New_York', true, true, CommitmentWindowType::Delivery);
        self::assertSame('2026-03-08T17:00:00.000000Z', $resolution->computedAt->toISOString());
        self::assertFalse($resolution->awaitingOperation);
        self::assertSame($policy, $resolution->policy);
        self::assertSame('2026-03-07T17:00:00.000000Z', $context->acceptedAt->utc()->toISOString());
    }

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

    private function resolve(array $policy, array $windows, array $context, string $timezone, bool $includeHolidays,
        bool $requireSelection, string $kind, ?callable $isHoliday = null): array
    {
        $result = (new CommitmentClock)->resolve(CommitmentInput::timingPolicy($policy), array_map(CommitmentInput::window(...), $windows),
            CommitmentInput::context(OfferingSelectionInput::fromArray($context), CarbonImmutable::parse('2026-09-14T00:00:00Z')), $timezone,
            $includeHolidays, $requireSelection, CommitmentWindowType::from($kind), $isHoliday);

        return CommitmentResolutionDocument::serialize($result, $policy);
    }
}
