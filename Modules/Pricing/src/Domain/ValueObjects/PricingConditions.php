<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class PricingConditions
{
    /** @param list<PricingCondition> $comparisons */
    public function __construct(public array $comparisons = [], public bool $matchesUnsupportedFacts = true) {}
}
