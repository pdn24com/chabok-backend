<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use Modules\Pricing\Application\Mappers\PricingCalculationInput;
use Modules\Pricing\Application\Services\PricingRuleCalculator;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\ChargeCategory;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\PricingRule;
use PHPUnit\Framework\TestCase;

final class PricingPrecisionTest extends TestCase
{
    public function test_large_money_values_keep_their_last_rial(): void
    {
        $rule = $this->rateRule('1.000000');
        $result = (new PricingRuleCalculator)->calculate([$rule], new CalculationFacts(declaredValueAmount: 9_007_199_254_740_993));
        self::assertSame(9_007_199_254_740_993, $result->totalAmount);
    }

    public function test_six_decimal_rates_round_half_a_rial_up_exactly(): void
    {
        $result = (new PricingRuleCalculator)->calculate([$this->rateRule('0.000001')], new CalculationFacts(declaredValueAmount: 500_000));
        self::assertSame(1, $result->totalAmount);
    }

    public function test_invalid_fixed_price_does_not_silently_become_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $rule = new PricingRule('72627359', '48747201', 'BASE', 'Base', ChargeCategory::BASE, CalculationMethod::FIXED, PricingBasis::FLAT, 10, null);
        (new PricingRuleCalculator)->calculate([$rule], new CalculationFacts);
    }

    public function test_legacy_conditions_preserve_strict_numeric_and_absent_fact_matching(): void
    {
        $record = ['rate_rule_id' => '72627359', 'charge_type_id' => '48747201', 'charge_type_code' => 'BASE', 'category' => 'BASE',
            'calculation_method' => 'FIXED', 'basis' => 'FLAT', 'priority' => 1, 'accounting_mapping_key' => null,
            'fixed_amount' => 10, 'conditions' => ['parcel_count' => 2, 'missing' => null]];
        $calculator = new PricingRuleCalculator;
        self::assertSame(10, $calculator->calculate(PricingCalculationInput::rules([$record]), new CalculationFacts(parcelCount: 2))->totalAmount);
        $record['conditions']['parcel_count'] = 2.0;
        self::assertSame(0, $calculator->calculate(PricingCalculationInput::rules([$record]), new CalculationFacts(parcelCount: 2))->totalAmount);
    }

    private function rateRule(string $rate): PricingRule
    {
        return new PricingRule('72627359', '48747201', 'BASE', 'Base', ChargeCategory::BASE, CalculationMethod::PER_UNIT,
            PricingBasis::DECLARED_VALUE, 10, null, unitRate: $rate);
    }
}
