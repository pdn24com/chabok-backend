<?php

declare(strict_types=1);
namespace Tests\Unit;
use Modules\Pricing\Domain\FreightMatrices;
use Modules\Pricing\Domain\DeterministicCalculator;
use PHPUnit\Framework\TestCase;
final class IncrementalFreightTest extends TestCase {
    public function test_half_and_full_steps_are_rounded_up_above_the_exact_threshold(): void {
        $engine = new DeterministicCalculator();
        $rule = ['rate_rule_id'=>'tail', 'matrix_cell_id'=>'tail','charge_type_id'=>'base','charge_type_code'=>'BASE_FREIGHT','category'=>'BASE','accounting_mapping_key'=>'base','calculation_method'=>'SLAB','basis'=>'BILLABLE_WEIGHT','range_from'=>10,'range_to'=>null,'fixed_amount'=>500000,'unit_rate'=>100000,'incremental_step_kg'=>1,'percentage_bps'=>null,'priority'=>10];
        foreach ([[10,500000],[10.1,600000],[11,600000],[11.2,700000],[1000,99500000]] as [$weight,$expected]) self::assertSame($expected,$engine->calculate([$rule],['billable_weight_kg'=>$weight])['total_amount']);
        $rule['incremental_step_kg']=0.5;
        foreach ([[10.1,600000],[10.5,600000],[10.6,700000]] as [$weight,$expected]) self::assertSame($expected,$engine->calculate([$rule],['billable_weight_kg'=>$weight])['total_amount']);
    }
    public function test_tail_requires_a_matching_boundary_and_a_priced_base(): void {
        $policy=new FreightMatrices();
        $matrix=['id'=>'m','service_offering_version_id'=>'s','origin_zone_id'=>'z','zone_ids'=>['z'],'bands'=>[['id'=>'b','from'=>0,'to'=>10,'cells'=>[['id'=>'base','zone_id'=>'z','state'=>'RATE','amount'=>500000]]]],'linear_tail'=>['id'=>'t','from'=>10,'step_kg'=>0.5,'cells'=>[['id'=>'inc','zone_id'=>'z','state'=>'RATE','amount'=>100000]]]];
        self::assertSame([],$policy->validate([$matrix],['z'],'DIRECTIONAL',true));
        self::assertSame(500000,$policy->compile([$matrix],'base')[1]['fixed_amount']);
        $matrix['linear_tail']['from']=9;
        self::assertContains('PRICING_LINEAR_TAIL_INVALID',array_column($policy->validate([$matrix],['z'],'DIRECTIONAL'),'code'));
        $matrix['linear_tail']['from']=10; $matrix['bands'][0]['cells'][0]['state']='EMPTY';$matrix['bands'][0]['cells'][0]['amount']=null;
        self::assertContains('PRICING_LINEAR_BASE_REQUIRED',array_column($policy->validate([$matrix],['z'],'DIRECTIONAL'),'code'));
    }
}
