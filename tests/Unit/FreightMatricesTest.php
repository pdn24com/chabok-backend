<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Application\Mappers\FreightMatrixInput;
use Modules\Pricing\Application\Mappers\MatrixRateRuleInput;
use Modules\Pricing\Application\Serialization\MatrixValidationSerializer;
use Modules\Pricing\Application\Services\FreightMatrixRuleCompiler;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Policies\ZoneRankPolicy;
use Modules\Pricing\Domain\Validators\FreightMatrixValidator;
use PHPUnit\Framework\TestCase;

final class FreightMatricesTest extends TestCase
{
    public function test_blank_is_draft_only_and_exclusion_is_not_a_zero_rule(): void
    {
        $matrix = $this->matrix();
        self::assertSame([], MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['A', 'B'], ZonePolicy::DIRECTIONAL)));
        self::assertContains('PRICING_MATRIX_RATE_MISSING', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['A', 'B'], ZonePolicy::DIRECTIONAL, true)), 'code'));
        $matrix['bands'][0]['cells'][0]['state'] = 'UNCOVERED';
        self::assertSame([], MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['A', 'B'], ZonePolicy::DIRECTIONAL, true)));
        self::assertCount(1, MatrixRateRuleInput::drafts((new FreightMatrixRuleCompiler)->compile(FreightMatrixInput::many([$matrix]), '212756002')));
        self::assertSame('B', MatrixRateRuleInput::drafts((new FreightMatrixRuleCompiler)->compile(FreightMatrixInput::many([$matrix]), '212756002'))[0]->destinationZoneId);
    }

    public function test_ranks_are_complete_positive_unique_and_never_inferred_from_codes(): void
    {
        self::assertFalse((new ZoneRankPolicy)->valid(array_map(fn ($zone) => $zone['rank'] ?? null, [['code' => 'A'], ['code' => 'B']])));
        self::assertFalse((new ZoneRankPolicy)->valid(array_map(fn ($zone) => $zone['rank'] ?? null, [['rank' => 1], ['rank' => 1]])));
        self::assertTrue((new ZoneRankPolicy)->valid(array_map(fn ($zone) => $zone['rank'] ?? null, [['rank' => 3], ['rank' => 9]])));
    }

    public function test_context_policy_zero_overlap_and_gap_fail_closed(): void
    {
        $matrix = $this->matrix();
        self::assertContains('PRICING_MATRIX_POLICY_MISMATCH', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['A', 'B'], ZonePolicy::HIGHER_ZONE_RANK)), 'code'));
        $matrix['bands'][0]['cells'][1]['amount'] = 0;
        self::assertContains('PRICING_BASE_RATE_INVALID', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['A', 'B'], ZonePolicy::DIRECTIONAL)), 'code'));
        $second = $matrix['bands'][0];
        $second['id'] = 'second';
        $second['from'] = 1;
        $second['to'] = 3;
        $matrix['bands'][] = $second;
        self::assertContains('PRICING_RULE_RANGE_OVERLAP', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['A', 'B'], ZonePolicy::DIRECTIONAL)), 'code'));
        $matrix['bands'][1]['from'] = 2.5;
        self::assertContains('PRICING_MATRIX_RANGE_GAP', array_column(MatrixValidationSerializer::serialize((new FreightMatrixValidator)->validate(FreightMatrixInput::many([$matrix]), ['A', 'B'], ZonePolicy::DIRECTIONAL, true)), 'code'));
    }

    private function matrix(): array
    {
        return ['id' => '115346646', 'service_offering_version_id' => '165636867', 'service_option_version_id' => null, 'origin_zone_id' => 'A', 'zone_ids' => ['A', 'B'], 'bands' => [['id' => '106894799', 'from' => 0.5, 'to' => 2, 'cells' => [['id' => '212432914', 'zone_id' => 'A', 'state' => 'EMPTY', 'amount' => null], ['id' => '65158786', 'zone_id' => 'B', 'state' => 'RATE', 'amount' => 1000]]]]];
    }
}
