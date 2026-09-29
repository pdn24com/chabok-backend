<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use Modules\Pricing\Application\Dto\TariffMatrixSelectionDto;
use Modules\Pricing\Application\Mappers\FreightMatrixInput;
use Modules\Pricing\Application\Services\FreightMatrixRuleCompiler;
use Modules\Pricing\Application\Services\PricingMatrixMatcher;
use Modules\Pricing\Domain\Enums\MatrixValidationCode;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Exceptions\InvalidPricingConfiguration;
use Modules\Pricing\Domain\Validators\FreightMatrixValidator;
use Modules\ServiceCatalog\Application\Contracts\CatalogResolverInterface;
use Modules\ServiceCatalog\Application\Dto\OptionRevisionSetDto;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FreightMatrixPipelineTest extends TestCase
{
    public function test_legacy_tail_is_normalized_without_mutating_saved_metadata(): void
    {
        $saved = $this->matrix();
        $saved['linear_tail'] = ['id' => '12988296', 'from' => 1, 'step_kg' => 0.1, 'cells' => [['id' => '135303194', 'zone_id' => '190608731', 'state' => 'RATE', 'amount' => 3]]];
        $matrix = FreightMatrixInput::fromArray($saved);

        self::assertCount(1, $matrix->linearBands);
        self::assertNull($matrix->linearBands[0]->to);
        self::assertArrayNotHasKey('to', $saved['linear_tail']);
        self::assertArrayNotHasKey('linear_bands', $saved);
        self::assertSame([], (new FreightMatrixValidator)->validate([$matrix], ['25296341', '190608731'], ZonePolicy::DIRECTIONAL, true));
    }

    public function test_invalid_state_produces_a_typed_issue_at_the_original_field_path(): void
    {
        $input = $this->matrix();
        $input['bands'][0]['cells'][0]['state'] = 'UNKNOWN';
        $input['bands'][0]['cells'][0]['amount'] = null;

        $issues = (new FreightMatrixValidator)->validate(FreightMatrixInput::many([$input]), ['25296341', '190608731'], ZonePolicy::DIRECTIONAL);

        self::assertCount(1, $issues);
        self::assertSame(MatrixValidationCode::MATRIX_STATE_INVALID, $issues[0]->code);
        self::assertSame('freight_matrices.0.bands.0.cells.0', $issues[0]->field);
    }

    public function test_compiler_carries_exact_rials_across_decimal_steps(): void
    {
        $input = $this->matrix();
        $input['bands'][0]['cells'][0]['amount'] = 9007199254740993;
        $input['linear_bands'] = [
            ['id' => '223303308', 'from' => '1.0000', 'to' => '1.3000', 'step_kg' => '0.1000', 'cells' => [['id' => '28653018', 'zone_id' => '190608731', 'state' => 'RATE', 'amount' => 7]]],
            ['id' => '36999066', 'from' => '1.3000', 'to' => null, 'step_kg' => '0.1000', 'cells' => [['id' => '159095489', 'zone_id' => '190608731', 'state' => 'RATE', 'amount' => 7]]],
        ];

        $rules = (new FreightMatrixRuleCompiler)->compile(FreightMatrixInput::many([$input]), '158632188');

        self::assertSame(9007199254740993, $rules[1]->fixedAmount);
        self::assertSame(9007199254741014, $rules[2]->fixedAmount);
    }

    #[DataProvider('rateAmounts')]
    public function test_rate_validation_preserves_integer_rials(int|float|string|null $amount, bool $valid): void
    {
        $input = $this->matrix();
        $input['bands'][0]['cells'][0]['amount'] = $amount;

        $issues = (new FreightMatrixValidator)->validate(FreightMatrixInput::many([$input]), ['25296341', '190608731'], ZonePolicy::DIRECTIONAL);

        if ($valid) {
            self::assertSame([], $issues);

            return;
        }

        self::assertCount(1, $issues);
        self::assertSame(MatrixValidationCode::BASE_RATE_INVALID, $issues[0]->code);
        self::assertSame('freight_matrices.0.bands.0.cells.0.amount', $issues[0]->field);
    }

    public static function rateAmounts(): iterable
    {
        yield 'integer above float precision' => ['9007199254740993', true];
        yield 'whole decimal' => ['100.000', true];
        yield 'scientific integer' => ['1e3', true];
        yield 'largest supported integer' => [(string) PHP_INT_MAX, true];
        yield 'fraction above float precision' => ['9007199254740993.5', false];
        yield 'integer overflow' => ['9223372036854775808', false];
        yield '261199080' => [0, false];
        yield 'negative' => [-1, false];
        yield 'missing' => [null, false];
        yield 'not numeric' => ['abc', false];
        yield 'infinite' => [INF, false];
    }

    public function test_quantity_validation_accepts_float_representation_noise_but_rejects_extra_precision(): void
    {
        $input = $this->matrix();
        $input['bands'][0]['to'] = 0.1 + 0.2;
        $input['linear_tail'] = ['id' => '12988296', 'from' => '0.3000', 'step_kg' => '0.1000', 'cells' => [['id' => '135303194', 'zone_id' => '190608731', 'state' => 'RATE', 'amount' => 3]]];
        $validator = new FreightMatrixValidator;

        self::assertSame([], $validator->validate(FreightMatrixInput::many([$input]), ['25296341', '190608731'], ZonePolicy::DIRECTIONAL, true));

        $input['linear_tail']['step_kg'] = '0.10001';
        $issues = $validator->validate(FreightMatrixInput::many([$input]), ['25296341', '190608731'], ZonePolicy::DIRECTIONAL, true);
        self::assertContains(MatrixValidationCode::LINEAR_TAIL_INVALID, array_column($issues, 'code'));
    }

    public function test_compiler_rejects_linear_rate_without_a_preceding_price(): void
    {
        $input = $this->matrix();
        $input['bands'][0]['cells'][0]['state'] = 'UNCOVERED';
        $input['bands'][0]['cells'][0]['amount'] = null;
        $input['linear_tail'] = ['id' => '12988296', 'from' => 1, 'step_kg' => 1, 'cells' => [['id' => '135303194', 'zone_id' => '190608731', 'state' => 'RATE', 'amount' => 3]]];
        $this->expectException(InvalidPricingConfiguration::class);
        (new FreightMatrixRuleCompiler)->compile(FreightMatrixInput::many([$input]), '158632188');
    }

    public function test_matcher_resolves_each_catalog_reference_once_for_many_matrices(): void
    {
        $catalog = Mockery::mock(CatalogResolverInterface::class);
        $catalog->shouldReceive('relatedVersions')->once()->with(CatalogResource::Offering, '90771604')->andReturn(['91125876']);
        $catalog->shouldReceive('optionRevisions')->once()->with(['252288886'])->andReturn([new OptionRevisionSetDto('252288886', '168929119', ['selected-option'], 'selected-option')]);
        $matrices = [];
        for ($index = 0; $index < 40; $index++) {
            $matrices[] = [...$this->matrix(), 'id' => \Tests\Support\FixtureId::from('matrix-'.$index), 'origin_zone_id' => $index === 0 ? '25296341' : '227711138', 'service_option_version_id' => '252288886'];
        }
        $tariff = new TariffMatrixSelectionDto(FreightMatrixInput::many($matrices), ['25296341' => 'FROM', '227711138' => 'OTHER', '190608731' => 'TO'], false);

        $cell = (new PricingMatrixMatcher($catalog))->matrixCell($tariff, '90771604', ['selected-option'], 'FROM', 'TO', 0.5);

        self::assertSame('60621493', $cell);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function matrix(): array
    {
        return [
            'id' => '115346646',
            'service_offering_version_id' => '91125876',
            'origin_zone_id' => '25296341',
            'zone_ids' => ['190608731'],
            'bands' => [['id' => '106894799', 'from' => 0, 'to' => 1, 'cells' => [['id' => '60621493', 'zone_id' => '190608731', 'state' => 'RATE', 'amount' => 100]]]],
        ];
    }
}
