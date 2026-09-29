<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Application\Services\PricingFacts;
use Modules\Pricing\Domain\Enums\WeightRoundingMode;
use Modules\Pricing\Domain\Exceptions\InvalidPricingInput;
use Modules\Pricing\Domain\ValueObjects\ParcelMeasurement;
use Modules\Pricing\Domain\ValueObjects\ShipmentMeasurements;
use Modules\Pricing\Domain\ValueObjects\WeightPricingPolicy;
use PHPUnit\Framework\TestCase;

final class PricingFactsTest extends TestCase
{
    public function test_volumetric_and_actual_weights_round_each_parcel_before_summing(): void
    {
        $facts = (new PricingFacts)->facts(new ShipmentMeasurements([
            new ParcelMeasurement(0.6, 20, 20, 20), new ParcelMeasurement(0.6),
        ], aggregate: new ParcelMeasurement(100), declaredValueAmount: 10000, codAmount: 20000, insuranceEnabled: true, codEnabled: true),
            new WeightPricingPolicy(5000, 0.5, WeightRoundingMode::StepUp), true);
        self::assertSame(1.2, $facts->actualWeightKg);
        self::assertSame(3.0, $facts->billableWeightKg);
        self::assertSame(2, $facts->parcelCount);
        self::assertSame('PER_PARCEL', $facts->weightEvidence);
        self::assertSame(10000, $facts->declaredValueAmount);
        self::assertSame(20000, $facts->codAmount);
        self::assertTrue($facts->insuranceEnabled);
        self::assertTrue($facts->codEnabled);
        self::assertTrue($facts->remoteArea);
    }

    public function test_aggregate_fallback_and_each_rounding_mode_preserve_minimum_step(): void
    {
        foreach ([[WeightRoundingMode::HalfUp, 1.5], [WeightRoundingMode::HalfEven, 1.0], [WeightRoundingMode::Floor, 1.0], [WeightRoundingMode::Ceiling, 1.5], [WeightRoundingMode::StepUp, 1.5]] as [$mode, $expected]) {
            $policy = new WeightPricingPolicy(5000, 0.5, $mode);
            $facts = (new PricingFacts)->facts(new ShipmentMeasurements([], new ParcelMeasurement(1.25)), $policy, false);
            self::assertSame($expected, $facts->billableWeightKg);
            self::assertSame(1, $facts->parcelCount);
            self::assertSame('AGGREGATE_FALLBACK', $facts->weightEvidence);
            self::assertFalse($facts->remoteArea);
            self::assertSame(0.5, $policy->round(0.01));
        }
    }

    public function test_invalid_measurements_and_weight_policy_fail_before_arithmetic(): void
    {
        $invalid = [fn () => new ParcelMeasurement(0), fn () => new ParcelMeasurement(INF),
            fn () => new ParcelMeasurement(1, 2), fn () => new ParcelMeasurement(1, 1, -2, 3),
            fn () => new WeightPricingPolicy(0, 0.5, WeightRoundingMode::StepUp),
            fn () => new WeightPricingPolicy(5000, NAN, WeightRoundingMode::Floor),
            fn () => (new PricingFacts)->facts(new ShipmentMeasurements([]), new WeightPricingPolicy(5000, 0.5, WeightRoundingMode::StepUp), false)];
        foreach ($invalid as $construct) {
            try {
                $construct();
                self::fail('Invalid input must be rejected.');
            } catch (InvalidPricingInput $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
    }
}
