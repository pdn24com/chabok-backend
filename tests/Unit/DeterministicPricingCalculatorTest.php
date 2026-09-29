<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Application\Mappers\PricingCalculationInput;
use Modules\Pricing\Application\Serialization\PricingCalculationSerializer;
use Modules\Pricing\Application\Services\PricingRuleCalculator;
use PHPUnit\Framework\TestCase;

final class DeterministicPricingCalculatorTest extends TestCase
{
    public function test_all_milestone_one_methods_reconcile_deterministically(): void
    {
        $rules = [$this->rule('212756002', 'BASE_FREIGHT', 'BASE', 'FIXED', fixed: 1000, priority: 10), $this->rule('89171103', 'EXTRA_PARCEL', 'SURCHARGE', 'PER_UNIT', basis: 'PARCEL_COUNT', rate: 100, priority: 20), $this->rule('90455835', 'DELIVERY_FEE', 'SURCHARGE', 'SLAB', from: 2, to: 4, fixed: 500, priority: 30), $this->rule('179416747', 'PICKUP_FEE', 'SURCHARGE', 'TIERED', from: 0, to: 2, rate: 100, priority: 40), $this->rule('3526541', 'PICKUP_FEE', 'SURCHARGE', 'TIERED', from: 2, to: 4, rate: 200, priority: 41), $this->rule('181134528', 'FUEL_SURCHARGE', 'SURCHARGE', 'PERCENT', percentage: 1000, bases: ['BASE_FREIGHT'], priority: 50), $this->rule('105810232', 'INSURANCE_FEE', 'SURCHARGE', 'MIN_MAX', basis: 'DECLARED_VALUE', rate: 0.01, minimum: 200, maximum: 1000, priority: 60), $this->rule('191961503', 'REMOTE_AREA', 'SURCHARGE', 'FIXED', fixed: 250, conditions: ['remote_area' => true], priority: 61), $this->rule('82665638', 'COD_FEE', 'SURCHARGE', 'PERCENT', basis: 'COD_AMOUNT', percentage: 200, conditions: ['cod_enabled' => true], priority: 62), $this->rule('35742068', 'DISCOUNT', 'DISCOUNT', 'FIXED', fixed: 50, priority: 70), $this->rule('33538113', 'TAX', 'TAX', 'PERCENT', percentage: 1000, bases: ['BASE_FREIGHT', 'DELIVERY_FEE'], priority: 80)];
        $facts = ['actual_weight_kg' => 2.5, 'billable_weight_kg' => 3.0, 'parcel_count' => 3, 'declared_value_amount' => 10000, 'cod_amount' => 5000, 'insurance_enabled' => true, 'cod_enabled' => true, 'remote_area' => true];
        $calculator = new PricingRuleCalculator;
        $first = PricingCalculationSerializer::serialize($calculator->calculate(PricingCalculationInput::rules($rules), PricingCalculationInput::facts($facts)));
        $second = PricingCalculationSerializer::serialize($calculator->calculate(PricingCalculationInput::rules(array_reverse($rules)), PricingCalculationInput::facts($facts)));
        self::assertSame($first, $second);
        self::assertSame(2850, $first['subtotal_amount']);
        self::assertSame(50, $first['discount_amount']);
        self::assertSame(150, $first['tax_amount']);
        self::assertSame(2950, $first['total_amount']);
        self::assertCount(11, $first['lines']);
        self::assertSame(['REMOTE_AREA', 'COD_FEE'], array_values(array_intersect(array_column($first['lines'], 'charge_code'), ['REMOTE_AREA', 'COD_FEE'])));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['result_fingerprint']);
    }

    public function test_insurance_percentage_ceil_rounds_to_independent_money_step_with_evidence(): void
    {
        $rule = $this->rule('105810232', 'INSURANCE', 'SURCHARGE', 'PERCENT', basis: 'DECLARED_VALUE', percentage: 2, amountRoundingMode: 'CEIL', amountRoundingStep: 10000);
        $result = PricingCalculationSerializer::serialize((new PricingRuleCalculator)->calculate(PricingCalculationInput::rules([$rule]), PricingCalculationInput::facts(['actual_weight_kg' => 1, 'billable_weight_kg' => 1, 'parcel_count' => 1, 'declared_value_amount' => 290000000, 'cod_amount' => 0, 'insurance_enabled' => true, 'cod_enabled' => false, 'remote_area' => false])));
        self::assertSame(60000, $result['lines'][0]['amount']);
        self::assertSame(['service_tariff_version_id' => null, 'incremental_step' => null, 'range_from' => null, 'range_to' => null, 'incremental_step_kg' => null, 'base_amount' => null, 'declared_value_basis' => 290000000, 'percentage_bps' => 2, 'raw_amount' => 58000, 'amount_rounding_mode' => 'CEIL', 'amount_rounding_step' => 10000, 'final_amount' => 60000], $result['lines'][0]['explanation']);
    }

    /** @return array<string,mixed> */
    public function test_tax_excludes_non_taxable_lines_without_changing_surcharge_percentage_bases(): void
    {
        $base = $this->rule('212756002', 'BASE_FREIGHT', 'BASE', 'FIXED', fixed: 1000, priority: 10);
        $insurance = [...$this->rule('105810232', 'INSURANCE', 'SURCHARGE', 'FIXED', fixed: 500, priority: 20), 'taxable' => false];
        $fuel = $this->rule('181134528', 'FUEL_SURCHARGE', 'SURCHARGE', 'PERCENT', percentage: 1000, bases: ['BASE_FREIGHT', 'INSURANCE'], priority: 30);
        $tax = $this->rule('33538113', 'TAX', 'TAX', 'PERCENT', percentage: 1000, bases: ['BASE_FREIGHT', 'INSURANCE', 'FUEL_SURCHARGE'], priority: 40);
        $result = PricingCalculationSerializer::serialize((new PricingRuleCalculator)->calculate(PricingCalculationInput::rules([$base, $insurance, $fuel, $tax]), PricingCalculationInput::facts([])));
        self::assertSame(150, $result['lines'][2]['amount']);
        self::assertSame(115, $result['tax_amount']);
        self::assertSame(1765, $result['total_amount']);
    }

    /** @return array<string,mixed> */
    private function rule(
        string $id,
        string $code,
        string $category,
        string $method,
        string $basis = 'BILLABLE_WEIGHT',
        ?float $from = null,
        ?float $to = null,
        ?int $fixed = null,
        ?float $rate = null,
        ?int $percentage = null,
        ?int $minimum = null,
        ?int $maximum = null,
        array $bases = [],
        array $conditions = [],
        int $priority = 100,
        string $amountRoundingMode = 'NONE',
        ?int $amountRoundingStep = null,
    ): array {
        return ['rate_rule_id' => $id, 'charge_type_id' => \Tests\Support\FixtureId::from('ct-'.$id), 'charge_type_code' => $code, 'title' => $code, 'category' => $category, 'accounting_mapping_key' => $code, 'calculation_method' => $method, 'basis' => $basis, 'range_from' => $from, 'range_to' => $to, 'fixed_amount' => $fixed, 'unit_rate' => $rate, 'percentage_bps' => $percentage, 'minimum_amount' => $minimum, 'maximum_amount' => $maximum, 'amount_rounding_mode' => $amountRoundingMode, 'amount_rounding_step' => $amountRoundingStep, 'basis_charge_codes' => $bases, 'conditions' => $conditions, 'priority' => $priority];
    }
}
