<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Validators;

use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\PricingValidationCode;
use Modules\Pricing\Domain\ValueObjects\PricingValidationIssue;
use Modules\Pricing\Domain\ValueObjects\TariffRuleDefinition;

final class TariffRuleValidator
{
    public function validateRule(TariffRuleDefinition $rule): array
    {
        $errors = [];
        if (! $rule->validRange()) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::RANGE_INVALID, 'rules');
        }
        $missingRate = match ($rule->method) {
            CalculationMethod::FIXED => $rule->fixedAmount === null ? PricingValidationCode::FIXED_AMOUNT_REQUIRED : null,
            CalculationMethod::PER_UNIT, CalculationMethod::TIERED => $rule->unitRate === null ? PricingValidationCode::UNIT_RATE_REQUIRED : null,
            CalculationMethod::SLAB => $rule->fixedAmount === null && $rule->unitRate === null ? PricingValidationCode::SLAB_RATE_REQUIRED : null,
            CalculationMethod::PERCENT => $rule->percentageBps === null ? PricingValidationCode::PERCENTAGE_REQUIRED : null,
            CalculationMethod::MIN_MAX => $rule->minimumAmount === null && $rule->maximumAmount === null ? PricingValidationCode::MIN_MAX_BOUND_REQUIRED : null,
        };
        if ($missingRate !== null) {
            $errors[] = new PricingValidationIssue($missingRate, 'rules');
        }
        if ($rule->minimumAmount !== null && $rule->maximumAmount !== null && $rule->minimumAmount > $rule->maximumAmount) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::MIN_MAX_INVALID, 'rules');
        }
        if ($rule->roundingMode !== AmountRoundingMode::NONE && ($rule->roundingStep === null || $rule->roundingStep === 0)) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::AMOUNT_ROUNDING_STEP_REQUIRED, 'rules');
        }

        return $errors;
    }

    public function conflicts(array $rules): array
    {
        $duplicates = false;
        $overlaps = false;
        foreach ($rules as $index => $left) {
            for ($rightIndex = $index + 1; $rightIndex < count($rules); $rightIndex++) {
                $right = $rules[$rightIndex];
                if (! $left->selector->sameTarget($right->selector)) {
                    continue;
                }
                $duplicates = $duplicates || $left->sameRange($right);
                $overlaps = $overlaps || ($left->selector->basis === $right->selector->basis && $left->overlaps($right));
            }
        }
        $errors = [];
        if ($duplicates) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::RULE_AMBIGUOUS, 'rules');
        }
        if ($overlaps) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::RULE_RANGE_OVERLAP, 'rules');
        }

        return $errors;
    }
}
