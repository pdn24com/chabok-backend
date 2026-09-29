<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Exceptions\InvalidPricingInput;

final readonly class ParcelMeasurement
{
    public function __construct(public float $weightKg, public ?float $lengthCm = null, public ?float $widthCm = null, public ?float $heightCm = null)
    {
        if (! is_finite($weightKg) || $weightKg <= 0) {
            throw new InvalidPricingInput('pricing.positive_parcel_weight_is_required');
        }
        $dimensions = array_filter([$lengthCm, $widthCm, $heightCm], static fn (?float $dimension): bool => $dimension !== null);
        if ($dimensions !== [] && (count($dimensions) !== 3 || count(array_filter($dimensions, static fn (float $dimension): bool => is_finite($dimension) && $dimension > 0)) !== 3)) {
            throw new InvalidPricingInput('pricing.provide_all_three_positive_parcel_dimensions_omit');
        }
    }

    public function volumetricWeight(float $divisor): float
    {
        return $this->lengthCm === null ? 0.0 : $this->lengthCm * $this->widthCm * $this->heightCm / $divisor;
    }
}
