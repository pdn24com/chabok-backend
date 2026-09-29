<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class CalculationResult
{
    /** @param list<CalculationLine> $lines */
    public function __construct(
        public array $lines,
        public int $subtotalAmount,
        public int $discountAmount,
        public int $taxAmount,
        public int $totalAmount,
        public string $fingerprint,
    ) {}
}
