<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Modules\Pricing\Application\Dto\PricingRateRuleDraftDto;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\ValueObjects\CompiledMatrixRateRule;

final class MatrixRateRuleInput
{
    private const MATRIX_RULE_PRIORITY = 10;

    /** @param list<CompiledMatrixRateRule> $rules */
    public static function drafts(array $rules): array
    {
        return array_map(self::rule(...), $rules);
    }

    private static function rule(CompiledMatrixRateRule $rule): PricingRateRuleDraftDto
    {
        return new PricingRateRuleDraftDto(chargeTypeId: $rule->chargeTypeId, calculationMethod: CalculationMethod::SLAB,
            serviceOfferingVersionId: $rule->serviceOfferingVersionId, serviceOptionVersionId: $rule->serviceOptionVersionId,
            matrixCellId: $rule->cellId, originZoneId: $rule->originZoneId, destinationZoneId: $rule->destinationZoneId,
            basis: PricingBasis::BILLABLE_WEIGHT, rangeFrom: (string) $rule->rangeFrom,
            rangeTo: $rule->rangeTo === null ? null : (string) $rule->rangeTo, fixedAmount: (int) $rule->fixedAmount,
            unitRate: $rule->unitRate === null ? null : (string) $rule->unitRate,
            incrementalStepKg: $rule->incrementalStepKg === null ? null : (string) $rule->incrementalStepKg,
            priority: self::MATRIX_RULE_PRIORITY, conditions: [], basisChargeCodes: []);
    }
}
