<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Domain\FreightMatrices;
use PHPUnit\Framework\TestCase;

final class FreightMatricesTest extends TestCase
{
    public function test_blank_is_draft_only_and_exclusion_is_not_a_zero_rule(): void
    {
        $matrix = $this->matrix(); $policy = new FreightMatrices();
        self::assertSame([], $policy->validate([$matrix], ['A', 'B'], 'DIRECTIONAL'));
        self::assertContains('PRICING_MATRIX_RATE_MISSING', array_column($policy->validate([$matrix], ['A', 'B'], 'DIRECTIONAL', true), 'code'));
        $matrix['bands'][0]['cells'][0]['state'] = 'UNCOVERED';
        self::assertSame([], $policy->validate([$matrix], ['A', 'B'], 'DIRECTIONAL', true));
        self::assertCount(1, $policy->compile([$matrix], 'base'));
        self::assertSame('B', $policy->compile([$matrix], 'base')[0]['destination_zone_id']);
    }

    public function test_ranks_are_complete_positive_unique_and_never_inferred_from_codes(): void
    {
        $policy = new FreightMatrices();
        self::assertFalse($policy->ranksValid([['code' => 'A'], ['code' => 'B']]));
        self::assertFalse($policy->ranksValid([['rank' => 1], ['rank' => 1]]));
        self::assertTrue($policy->ranksValid([['rank' => 3], ['rank' => 9]]));
    }

    public function test_context_policy_zero_overlap_and_gap_fail_closed(): void
    {
        $policy = new FreightMatrices(); $matrix = $this->matrix();
        self::assertContains('PRICING_MATRIX_POLICY_MISMATCH', array_column($policy->validate([$matrix], ['A', 'B'], 'HIGHER_ZONE_RANK'), 'code'));
        $matrix['bands'][0]['cells'][1]['amount'] = 0;
        self::assertContains('PRICING_BASE_RATE_INVALID', array_column($policy->validate([$matrix], ['A', 'B'], 'DIRECTIONAL'), 'code'));
        $second = $matrix['bands'][0]; $second['id'] = 'second'; $second['from'] = 1; $second['to'] = 3;
        $matrix['bands'][] = $second;
        self::assertContains('PRICING_RULE_RANGE_OVERLAP', array_column($policy->validate([$matrix], ['A', 'B'], 'DIRECTIONAL'), 'code'));
        $matrix['bands'][1]['from'] = 2.5;
        self::assertContains('PRICING_MATRIX_RANGE_GAP', array_column($policy->validate([$matrix], ['A', 'B'], 'DIRECTIONAL', true), 'code'));
    }

    private function matrix(): array
    {
        return ['id' => 'matrix', 'service_offering_version_id' => 'service', 'service_option_version_id' => null, 'origin_zone_id' => 'A', 'zone_ids' => ['A', 'B'], 'bands' => [
            ['id' => 'band', 'from' => 0.5, 'to' => 2, 'cells' => [
                ['id' => 'a', 'zone_id' => 'A', 'state' => 'EMPTY', 'amount' => null],
                ['id' => 'b', 'zone_id' => 'B', 'state' => 'RATE', 'amount' => 1000],
            ]],
        ]];
    }
}
