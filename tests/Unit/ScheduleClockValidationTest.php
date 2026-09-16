<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Modules\ServiceCatalog\Presentation\Http\Requests\CatalogRequestRules;
use PHPUnit\Framework\TestCase;

final class ScheduleClockValidationTest extends TestCase
{
    public function test_existing_seconds_and_minute_precision_are_valid_but_invalid_clocks_are_rejected(): void
    {
        $rules = CatalogRequestRules::scheduleRules(false);
        $factory = new Factory(new Translator(new ArrayLoader(), 'en'));
        foreach (['start_time', 'end_time', 'booking_cutoff_time'] as $field) {
            foreach (['09:25', '09:25:36', '23:59:59', '00:00:00'] as $clock) {
                self::assertTrue($factory->make(['clock' => $clock], ['clock' => $rules['windows.*.' . $field]])->passes(), $clock);
            }
            foreach (['24:00', '09:60', '09:25:60', 'not-a-clock'] as $clock) {
                self::assertFalse($factory->make(['clock' => $clock], ['clock' => $rules['windows.*.' . $field]])->passes(), $clock);
            }
        }
    }
}
