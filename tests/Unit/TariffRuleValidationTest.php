<?php

declare(strict_types=1);

namespace Tests\Unit;

use Brick\Math\BigDecimal;
use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\Enums\PricingValidationCode;
use Modules\Pricing\Domain\Validators\TariffRuleValidator;
use Modules\Pricing\Domain\ValueObjects\TariffRuleDefinition;
use Modules\Pricing\Domain\ValueObjects\TariffRuleSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TariffRuleValidationTest extends TestCase
{
    #[DataProvider('missingRates')]
    public function test_each_calculation_method_requires_its_own_rate(CalculationMethod $method, PricingValidationCode $expected): void
    {
        $issues = (new TariffRuleValidator)->validateRule($this->rule(method: $method));
        self::assertSame([$expected], array_column($issues, 'code'));
    }

    public static function missingRates(): iterable
    {
        yield [CalculationMethod::FIXED, PricingValidationCode::FIXED_AMOUNT_REQUIRED];
        yield [CalculationMethod::PER_UNIT, PricingValidationCode::UNIT_RATE_REQUIRED];
        yield [CalculationMethod::TIERED, PricingValidationCode::UNIT_RATE_REQUIRED];
        yield [CalculationMethod::SLAB, PricingValidationCode::SLAB_RATE_REQUIRED];
        yield [CalculationMethod::PERCENT, PricingValidationCode::PERCENTAGE_REQUIRED];
        yield [CalculationMethod::MIN_MAX, PricingValidationCode::MIN_MAX_BOUND_REQUIRED];
    }

    public function test_decimal_bounds_do_not_collapse_to_the_same_float(): void
    {
        $rule = $this->rule('9007199254740992.0001', '9007199254740992.0002', fixed: 0);
        self::assertTrue($rule->validRange());
        self::assertSame([], (new TariffRuleValidator)->validateRule($rule));
    }

    public function test_adjacent_half_open_ranges_do_not_overlap_and_unbounded_ranges_do(): void
    {
        $validator = new TariffRuleValidator;
        $left = $this->rule(null, '1.0001');
        $right = $this->rule('1.0001', null);
        self::assertSame([], $validator->conflicts([$left, $right]));
        self::assertSame([PricingValidationCode::RULE_RANGE_OVERLAP], array_column($validator->conflicts([$left, $this->rule('1.0000', null)]), 'code'));
    }

    public function test_duplicate_detection_compares_named_selector_fields(): void
    {
        $validator = new TariffRuleValidator;
        $rule = $this->rule('1', '2');
        $equivalent = $this->rule('1.0000', '2.0000');
        self::assertSame([PricingValidationCode::RULE_AMBIGUOUS, PricingValidationCode::RULE_RANGE_OVERLAP], array_column($validator->conflicts([$rule, $equivalent]), 'code'));
        self::assertSame([], $validator->conflicts([$rule, $this->rule('1', '2', offering: 'another-offering')]));
    }

    public function test_basis_changes_overlap_selection_but_not_exact_duplicate_identity(): void
    {
        $left = $this->rule('0', '2');
        $right = $this->rule('1', '3', basis: PricingBasis::PARCEL_COUNT);
        self::assertSame([], (new TariffRuleValidator)->conflicts([$left, $right]));
        $duplicate = $this->rule('0', '2', basis: PricingBasis::PARCEL_COUNT);
        self::assertSame([PricingValidationCode::RULE_AMBIGUOUS], array_column((new TariffRuleValidator)->conflicts([$left, $duplicate]), 'code'));
    }

    public function test_invalid_or_empty_ranges_do_not_create_spurious_overlap_issues(): void
    {
        $invalid = $this->rule('2', '1', fixed: 1);
        self::assertSame([PricingValidationCode::RANGE_INVALID], array_column((new TariffRuleValidator)->validateRule($invalid), 'code'));
        self::assertSame([], (new TariffRuleValidator)->conflicts([$invalid, $this->rule(null, null)]));
    }

    private function rule(?string $from = null, ?string $to = null, CalculationMethod $method = CalculationMethod::FIXED, ?int $fixed = null, string $offering = '90771604', PricingBasis $basis = PricingBasis::BILLABLE_WEIGHT): TariffRuleDefinition
    {
        return new TariffRuleDefinition(new TariffRuleSelector($offering, null, null, null, '158632188', 10, $basis), $method,
            $from === null ? null : BigDecimal::of($from), $to === null ? null : BigDecimal::of($to), $fixed, null, null, null, null, AmountRoundingMode::NONE, null);
    }
}
