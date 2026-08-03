<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Domain\DeterministicCalculator;
use PHPUnit\Framework\TestCase;

final class DeterministicPricingCalculatorTest extends TestCase
{
    public function test_all_milestone_one_methods_reconcile_deterministically(): void
    {
        $rules = [
            $this->rule('base', 'BASE_FREIGHT', 'BASE', 'FIXED', fixed: 1000, priority: 10),
            $this->rule('parcel', 'EXTRA_PARCEL', 'SURCHARGE', 'PER_UNIT', basis: 'PARCEL_COUNT', rate: 100, priority: 20),
            $this->rule('slab', 'DELIVERY_FEE', 'SURCHARGE', 'SLAB', from: 2, to: 4, fixed: 500, priority: 30),
            $this->rule('tier-1', 'PICKUP_FEE', 'SURCHARGE', 'TIERED', from: 0, to: 2, rate: 100, priority: 40),
            $this->rule('tier-2', 'PICKUP_FEE', 'SURCHARGE', 'TIERED', from: 2, to: 4, rate: 200, priority: 41),
            $this->rule('fuel', 'FUEL_SURCHARGE', 'SURCHARGE', 'PERCENT', percentage: 1000, bases: ['BASE_FREIGHT'], priority: 50),
            $this->rule('insurance', 'INSURANCE_FEE', 'SURCHARGE', 'MIN_MAX', basis: 'DECLARED_VALUE', rate: 0.01, minimum: 200, maximum: 1000, priority: 60),
            $this->rule('remote', 'REMOTE_AREA', 'SURCHARGE', 'FIXED', fixed: 250, conditions: ['remote_area' => true], priority: 61),
            $this->rule('cod', 'COD_FEE', 'SURCHARGE', 'PERCENT', basis: 'COD_AMOUNT', percentage: 200, conditions: ['cod_enabled' => true], priority: 62),
            $this->rule('discount', 'DISCOUNT', 'DISCOUNT', 'FIXED', fixed: 50, priority: 70),
            $this->rule('tax', 'TAX', 'TAX', 'PERCENT', percentage: 1000, bases: ['BASE_FREIGHT', 'DELIVERY_FEE'], priority: 80),
        ];
        $facts = ['actual_weight_kg' => 2.5, 'billable_weight_kg' => 3.0, 'parcel_count' => 3, 'declared_value_amount' => 10000, 'cod_amount' => 5000, 'insurance_enabled' => true, 'cod_enabled' => true, 'remote_area' => true];
        $calculator = new DeterministicCalculator();
        $first = $calculator->calculate($rules, $facts);
        $second = $calculator->calculate(array_reverse($rules), $facts);

        self::assertSame($first, $second);
        self::assertSame(2850, $first['subtotal_amount']);
        self::assertSame(50, $first['discount_amount']);
        self::assertSame(150, $first['tax_amount']);
        self::assertSame(2950, $first['total_amount']);
        self::assertCount(11, $first['lines']);
        self::assertSame(['REMOTE_AREA', 'COD_FEE'], array_values(array_intersect(array_column($first['lines'], 'charge_code'), ['REMOTE_AREA', 'COD_FEE'])));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['result_fingerprint']);
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
    ): array {
        return ['rate_rule_id' => $id, 'charge_type_id' => 'ct-'.$id, 'charge_type_code' => $code, 'title' => $code, 'category' => $category, 'accounting_mapping_key' => $code, 'calculation_method' => $method, 'basis' => $basis, 'range_from' => $from, 'range_to' => $to, 'fixed_amount' => $fixed, 'unit_rate' => $rate, 'percentage_bps' => $percentage, 'minimum_amount' => $minimum, 'maximum_amount' => $maximum, 'basis_charge_codes' => $bases, 'conditions' => $conditions, 'priority' => $priority];
    }
}
