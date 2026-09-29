<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\WeightRoundingMode;
use Modules\Pricing\Domain\Exceptions\InvalidPricingInput;

final readonly class WeightPricingPolicy
{
    public function __construct(public float $volumetricDivisor, public float $roundingStepKg, public WeightRoundingMode $roundingMode)
    {
        if (! is_finite($volumetricDivisor) || $volumetricDivisor <= 0 || ! is_finite($roundingStepKg) || $roundingStepKg <= 0) {
            throw new InvalidPricingInput('pricing.weight_divisor_and_rounding_step_must_be_positive');
        }
    }

    public function round(float $weight): float
    {
        $units = $weight / $this->roundingStepKg;
        $rounded = match ($this->roundingMode) {
            WeightRoundingMode::HalfUp => round($units, 0, PHP_ROUND_HALF_UP),
            WeightRoundingMode::HalfEven => round($units, 0, PHP_ROUND_HALF_EVEN),
            WeightRoundingMode::Floor => floor($units),
            WeightRoundingMode::Ceiling, WeightRoundingMode::StepUp => ceil($units),
        };

        return max($this->roundingStepKg, $rounded * $this->roundingStepKg);
    }
}
