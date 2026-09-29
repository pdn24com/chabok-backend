<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class CalculationLine
{
    public function __construct(
        public PricingRule $rule,
        public float $quantity,
        public int $rawAmount,
        public int $amount,
    ) {}
}
