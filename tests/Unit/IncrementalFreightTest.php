<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Application\Mappers\FreightMatrixInput;
use Modules\Pricing\Application\Mappers\MatrixRateRuleInput;
use Modules\Pricing\Application\Mappers\PricingCalculationInput;
use Modules\Pricing\Application\Serialization\MatrixValidationSerializer;
use Modules\Pricing\Application\Services\FreightMatrixRuleCompiler;
use Modules\Pricing\Application\Services\PricingRuleCalculator;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Validators\FreightMatrixValidator;
use PHPUnit\Framework\TestCase;

final class IncrementalFreightTest extends TestCase
{
    public function test_finite_segments_carry_endpoint_amounts_and_reject_gaps_or_nonfinal_infinity(): void
    {
        $cell = fn ($id, $amount) => ['id' => $id, 'zone_id' => '93644058', 'state' => 'RATE', 'amount' => $amount];
        $matrix = ['id' => '103573160', 'service_offering_version_id' => '4433689', 'origin_zone_id' => '93644058', 'zone_ids' => ['93644058'], 'bands' => [['id' => '65158786', 'from' => 0, 'to' => 10, 'cells' => [$cell('212756002', 500000)]]], 'linear_bands' => [['id' => '41962414', 'from' => 10, 'to' => 50, 'step_kg' => 1, 'cells' => [$cell('219112221', 10000)]], ['id' => '144821989', 'from' => 50, 'to' => 100, 'step_kg' => 0.5, 'cells' => [$cell('163621862', 20000)]], ['id' => '17673437', 'from' => 100, 'to' => null, 'step_kg' => 1, 'cells' => [$cell('130140542', 30000)]]]];
        self::assertSame([], MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['93644058'], ZonePolicy::DIRECTIONAL, true)));
        $rules = MatrixRateRuleInput::drafts((new FreightMatrixRuleCompiler)->compile(FreightMatrixInput::many([$matrix]), '212756002'));
        self::assertSame([500000, 500000, 900000, 2900000], array_column($rules, 'fixedAmount'));
        self::assertSame(['10', '50', '100', null], array_column($rules, 'rangeTo'));
        $mixed = $matrix;
        $mixed['linear_bands'] = [];
        $mixed['linear_tail'] = ['id' => '213518006', 'from' => 10, 'step_kg' => 1, 'cells' => [$cell('old-cell', 10000)]];
        self::assertContains('PRICING_LINEAR_TAIL_INVALID', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$mixed]), ['93644058'], ZonePolicy::DIRECTIONAL)), 'code'));
        $matrix['linear_bands'][1]['from'] = 51;
        self::assertContains('PRICING_LINEAR_TAIL_INVALID', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['93644058'], ZonePolicy::DIRECTIONAL)), 'code'));
        $matrix['linear_bands'][1]['from'] = 50;
        $matrix['linear_bands'][0]['to'] = null;
        self::assertContains('PRICING_LINEAR_TAIL_INVALID', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['93644058'], ZonePolicy::DIRECTIONAL)), 'code'));
    }

    public function test_half_and_full_steps_are_rounded_up_above_the_exact_threshold(): void
    {
        $engine = new PricingRuleCalculator;
        $rule = ['rate_rule_id' => '12988296', 'matrix_cell_id' => '12988296', 'charge_type_id' => '212756002', 'charge_type_code' => 'BASE_FREIGHT', 'category' => 'BASE', 'accounting_mapping_key' => '212756002', 'calculation_method' => 'SLAB', 'basis' => 'BILLABLE_WEIGHT', 'range_from' => 10, 'range_to' => null, 'fixed_amount' => 500000, 'unit_rate' => 100000, 'incremental_step_kg' => 1, 'percentage_bps' => null, 'priority' => 10];
        foreach ([[10, 500000], [10.1, 600000], [11, 600000], [11.2, 700000], [1000, 99500000]] as [$weight, $expected]) {
            self::assertSame($expected, $engine->calculate(PricingCalculationInput::rules([$rule]), PricingCalculationInput::facts(['billable_weight_kg' => $weight]))->totalAmount);
        }
        $rule['incremental_step_kg'] = 0.5;
        foreach ([[10.1, 600000], [10.5, 600000], [10.6, 700000]] as [$weight, $expected]) {
            self::assertSame($expected, $engine->calculate(PricingCalculationInput::rules([$rule]), PricingCalculationInput::facts(['billable_weight_kg' => $weight]))->totalAmount);
        }
    }

    public function test_tail_requires_a_matching_boundary_and_a_priced_base(): void
    {
        $matrix = ['id' => '103573160', 'service_offering_version_id' => '4433689', 'origin_zone_id' => '93644058', 'zone_ids' => ['93644058'], 'bands' => [['id' => '65158786', 'from' => 0, 'to' => 10, 'cells' => [['id' => '212756002', 'zone_id' => '93644058', 'state' => 'RATE', 'amount' => 500000]]]], 'linear_tail' => ['id' => '238786725', 'from' => 10, 'step_kg' => 0.5, 'cells' => [['id' => '262748219', 'zone_id' => '93644058', 'state' => 'RATE', 'amount' => 100000]]]];
        self::assertSame([], MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['93644058'], ZonePolicy::DIRECTIONAL, true)));
        self::assertSame(500000, MatrixRateRuleInput::drafts((new FreightMatrixRuleCompiler)->compile(FreightMatrixInput::many([$matrix]), '212756002'))[1]->fixedAmount);
        $matrix['linear_tail']['from'] = 9;
        self::assertContains('PRICING_LINEAR_TAIL_INVALID', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['93644058'], ZonePolicy::DIRECTIONAL)), 'code'));
        $matrix['linear_tail']['from'] = 10;
        $matrix['bands'][0]['cells'][0]['state'] = 'EMPTY';
        $matrix['bands'][0]['cells'][0]['amount'] = null;
        self::assertContains('PRICING_LINEAR_BASE_REQUIRED', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['93644058'], ZonePolicy::DIRECTIONAL)), 'code'));
    }
}
