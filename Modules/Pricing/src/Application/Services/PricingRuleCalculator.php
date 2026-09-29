<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Pricing\Application\Contracts\PricingRuleCalculatorInterface;
use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\ChargeCategory;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\Exceptions\InvalidPricingConfiguration;
use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\CalculationLine;
use Modules\Pricing\Domain\ValueObjects\CalculationResult;
use Modules\Pricing\Domain\ValueObjects\PricingRule;

final class PricingRuleCalculator implements PricingRuleCalculatorInterface
{
    private const QUANTITY_DECIMALS = 4;

    private const BASIS_POINTS = 10000;

    /** @param list<PricingRule> $rules */
    public function calculate(array $rules, CalculationFacts $facts): CalculationResult
    {
        usort($rules, $this->compareRules(...));
        $lines = [];
        $appliedSlabChargeCodes = [];
        foreach ($rules as $rule) {
            if (! $this->conditionsMatch($rule, $facts)) {
                continue;
            }
            $quantity = $this->quantity($rule->basis, $facts);
            $outsideRange = $rule->rangeFrom !== null && $quantity < $rule->rangeFrom || $rule->rangeTo !== null && $quantity >= $rule->rangeTo;
            if ($rule->method !== CalculationMethod::TIERED && $outsideRange) {
                continue;
            }
            if ($rule->method === CalculationMethod::SLAB && isset($appliedSlabChargeCodes[$rule->chargeCode])) {
                continue;
            }
            $rawAmount = match ($rule->method) {
                CalculationMethod::FIXED => $rule->fixedAmount ?? throw new InvalidPricingConfiguration('FIXED requires fixed_amount.'),
                CalculationMethod::PER_UNIT => $this->multiplyMoney($quantity, $this->unitRate($rule)),
                CalculationMethod::SLAB => $this->slabAmount($rule, $quantity),
                CalculationMethod::TIERED => $this->tierAmount($rule, $quantity),
                CalculationMethod::PERCENT => $this->percentageAmount($rule, $lines, $facts),
                CalculationMethod::MIN_MAX => $this->boundedAmount($rule, $lines, $facts, $quantity),
            };
            $amount = $this->roundAmount($rawAmount, $rule->roundingMode, $rule->roundingStep);
            if ($amount === 0 && $rule->method === CalculationMethod::TIERED) {
                continue;
            }
            if ($rule->method === CalculationMethod::SLAB) {
                $appliedSlabChargeCodes[$rule->chargeCode] = true;
            }
            $lines[] = new CalculationLine($rule, round($quantity, self::QUANTITY_DECIMALS), $rawAmount, max(0, $amount));
        }

        return $this->summarize($lines);
    }

    private function compareRules(PricingRule $left, PricingRule $right): int
    {
        return [$left->priority, $left->chargeCode, $left->rangeFrom ?? 0] <=> [$right->priority, $right->chargeCode, $right->rangeFrom ?? 0];
    }

    private function conditionsMatch(PricingRule $rule, CalculationFacts $facts): bool
    {
        if (! $rule->conditions->matchesUnsupportedFacts) {
            return false;
        }
        foreach ($rule->conditions->comparisons as $condition) {
            if ($facts->value($condition->fact) !== $condition->expected) {
                return false;
            }
        }

        return true;
    }

    private function quantity(PricingBasis $basis, CalculationFacts $facts): int|float
    {
        return match ($basis) {
            PricingBasis::FLAT, PricingBasis::SHIPMENT => 1,
            PricingBasis::ACTUAL_WEIGHT => $facts->actualWeightKg ?? 0,
            PricingBasis::BILLABLE_WEIGHT => $facts->billableWeightKg ?? 0,
            PricingBasis::PARCEL_COUNT => $facts->parcelCount ?? 0,
            PricingBasis::DECLARED_VALUE => $facts->declaredValueAmount ?? 0,
            PricingBasis::COD_AMOUNT => $facts->codAmount ?? 0,
        };
    }

    private function slabAmount(PricingRule $rule, int|float $quantity): int
    {
        $step = $rule->incrementalStep ?? $rule->incrementalStepKg;
        if ($step === null) {
            return $rule->fixedAmount ?? $this->multiplyMoney($quantity, $this->unitRate($rule));
        }
        $scaledStep = BigDecimal::of((string) $step)->toScale(self::QUANTITY_DECIMALS, RoundingMode::HalfUp);
        if ($scaledStep->isLessThanOrEqualTo(0)) {
            throw new InvalidPricingConfiguration('Incremental pricing requires a positive step.');
        }
        $excess = BigDecimal::of((string) $quantity)->toScale(self::QUANTITY_DECIMALS, RoundingMode::HalfUp)->minus(BigDecimal::of((string) ($rule->rangeFrom ?? 0))->toScale(self::QUANTITY_DECIMALS, RoundingMode::HalfUp));
        $units = $excess->isNegative() ? 0 : $excess->dividedBy($scaledStep, 0, RoundingMode::Ceiling)->toInt();
        $base = $rule->fixedAmount ?? throw new InvalidPricingConfiguration('Incremental pricing requires a base amount.');

        return BigDecimal::of($base)->plus($this->multiplyMoney($units, $this->unitRate($rule)))->toInt();
    }

    private function tierAmount(PricingRule $rule, int|float $quantity): int
    {
        $units = BigDecimal::of((string) min($quantity, $rule->rangeTo ?? $quantity))->minus((string) ($rule->rangeFrom ?? 0));
        if ($units->isNegative()) {
            return 0;
        }

        return $this->multiplyMoney((string) $units, $this->unitRate($rule));
    }

    /** @param list<CalculationLine> $lines */
    private function percentageAmount(
        PricingRule $rule,
        array $lines,
        CalculationFacts $facts,
    ): int {
        $percentage = $rule->percentageBps ?? throw new InvalidPricingConfiguration('PERCENT requires percentage_bps.');
        $base = BigDecimal::of((string) $this->quantity($rule->basis, $facts));
        if ($rule->basisChargeCodes !== []) {
            $base = BigDecimal::zero();
            foreach ($lines as $line) {
                if (! in_array($line->rule->chargeCode, $rule->basisChargeCodes, true)) {
                    continue;
                }
                $isTax = $rule->category === ChargeCategory::TAX;
                if ($isTax && (! $line->rule->taxable || $line->rule->category === ChargeCategory::TAX)) {
                    continue;
                }
                $isTaxDeduction = $isTax && $line->rule->category === ChargeCategory::DISCOUNT;
                $base = $base->plus($isTaxDeduction ? -$line->amount : $line->amount);
            }
        }
        if ($base->isNegative()) {
            $base = BigDecimal::zero();
        }

        return $base
            ->multipliedBy($percentage)
            ->dividedBy(self::BASIS_POINTS, 0, RoundingMode::HalfUp)
            ->toInt();
    }

    /** @param list<CalculationLine> $lines */
    private function boundedAmount(
        PricingRule $rule,
        array $lines,
        CalculationFacts $facts,
        int|float $quantity,
    ): int {
        if ($rule->minimumAmount === null && $rule->maximumAmount === null) {
            throw new InvalidPricingConfiguration('MIN_MAX requires an amount bound.');
        }
        if ($rule->percentageBps !== null) {
            $candidate = $this->percentageAmount($rule, $lines, $facts);
        } elseif ($rule->unitRate !== null) {
            $candidate = $this->multiplyMoney($quantity, $rule->unitRate);
        } else {
            // A bound-only rule starts at zero before applying its floor/cap.
            $candidate = $rule->fixedAmount ?? 0;
        }
        if ($rule->minimumAmount !== null) {
            $candidate = max($candidate, $rule->minimumAmount);
        }
        if ($rule->maximumAmount !== null) {
            $candidate = min($candidate, $rule->maximumAmount);
        }

        return $candidate;
    }

    private function unitRate(PricingRule $rule): string
    {
        return $rule->unitRate ?? throw new InvalidPricingConfiguration('This pricing method requires unit_rate.');
    }

    private function multiplyMoney(int|float|string $quantity, string $rate): int
    {
        return BigDecimal::of((string) $quantity)
            ->multipliedBy($rate)
            ->toScale(0, RoundingMode::HalfUp)
            ->toInt();
    }

    private function roundAmount(
        int $amount,
        AmountRoundingMode $mode,
        ?int $step,
    ): int {
        if ($mode === AmountRoundingMode::NONE) {
            return $amount;
        }
        if ($step === null || $step < 1) {
            throw new InvalidPricingConfiguration('Amount rounding requires a positive step.');
        }
        $rounding = match ($mode) {
            AmountRoundingMode::CEIL => RoundingMode::Ceiling,
            AmountRoundingMode::FLOOR => RoundingMode::Floor,
            AmountRoundingMode::HALF_UP => RoundingMode::HalfUp,
        };

        return BigDecimal::of($amount)
            ->dividedBy($step, 0, $rounding)
            ->multipliedBy($step)
            ->toInt();
    }

    /** @param list<CalculationLine> $lines */
    private function summarize(array $lines): CalculationResult
    {
        $subtotal = BigDecimal::zero();
        $discount = BigDecimal::zero();
        $tax = BigDecimal::zero();
        foreach ($lines as $line) {
            match ($line->rule->category) {
                ChargeCategory::BASE, ChargeCategory::SURCHARGE, ChargeCategory::COMMISSION => $subtotal = $subtotal->plus($line->amount),
                ChargeCategory::DISCOUNT => $discount = $discount->plus($line->amount),
                ChargeCategory::TAX => $tax = $tax->plus($line->amount),
            };
        }
        $total = max(0, $subtotal
            ->minus($discount)
            ->plus($tax)
            ->toInt());

        return new CalculationResult($lines, $subtotal->toInt(), $discount->toInt(), $tax->toInt(), $total, $this->fingerprint($lines, $subtotal->toInt(), $discount->toInt(), $tax->toInt(), $total));
    }

    /** @param list<CalculationLine> $lines */
    private function fingerprint(
        array $lines,
        int $subtotal,
        int $discount,
        int $tax,
        int $total,
    ): string {
        // This tuple order and numeric JSON representation are the existing
        // quote fingerprint contract. Display labels are deliberately excluded.
        $tuples = [];
        foreach ($lines as $line) {
            $tuples[] = [
                $line->rule->chargeCode,
                $line->rule->id,
                $line->quantity,
                $line->rule->unitRate === null ? null : (float) $line->rule->unitRate,
                $line->amount,
            ];
        }

        return hash('sha256', json_encode([$tuples, $subtotal, $discount, $tax, $total], JSON_THROW_ON_ERROR));
    }
}
