<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class MatrixQuantityPrecision
{
    public const DECIMAL_PLACES = 4;

    public const MAX_LINEAR_STEP = 99_999_999;

    private const SCALE = 10_000;

    // Accept the representation error of JSON floats, but not a fifth decimal place.
    private const REPRESENTATION_TOLERANCE = 0.00001;

    public static function isSupported(int|float|string|null $quantity): bool
    {
        if ($quantity === null || ! is_numeric($quantity)) {
            return false;
        }

        $value = (float) $quantity;

        return is_finite($value)
            && abs($value * self::SCALE - round($value * self::SCALE)) <= self::REPRESENTATION_TOLERANCE;
    }

    public static function decimal(int|float|string $quantity): BigDecimal
    {
        return BigDecimal::of(trim((string) $quantity))->toScale(self::DECIMAL_PLACES, RoundingMode::HalfUp);
    }
}
