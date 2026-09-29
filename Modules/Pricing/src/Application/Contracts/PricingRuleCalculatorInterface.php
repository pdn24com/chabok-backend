<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\CalculationResult;
use Modules\Pricing\Domain\ValueObjects\PricingRule;

interface PricingRuleCalculatorInterface
{
    /** @param list<PricingRule> $rules */
    public function calculate(array $rules, CalculationFacts $facts): CalculationResult;
}
