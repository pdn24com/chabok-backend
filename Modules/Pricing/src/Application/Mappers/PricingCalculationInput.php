<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\ChargeCategory;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\Enums\PricingFact;
use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\PricingCondition;
use Modules\Pricing\Domain\ValueObjects\PricingConditions;
use Modules\Pricing\Domain\ValueObjects\PricingRule;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffRateRuleRecord;

/** Normalize database decimals and legacy JSON before entering the calculator. */
final class PricingCalculationInput
{
    public static function fromRecord(TariffRateRuleRecord $record, ?string $serviceTariffVersionId = null): PricingRule
    {
        $charge = $record->chargeType;

        return self::rule([...$record->attributesToArray(), 'charge_type_code' => $charge->code, 'title' => $charge->code,
            'category' => $charge->category, 'accounting_mapping_key' => $charge->accounting_mapping_key,
            'taxable' => $record->taxable ?? $charge->taxable, 'service_tariff_version_id' => $serviceTariffVersionId]);
    }

    /** @param list<array<string, mixed>> $records @return list<PricingRule> */
    public static function rules(array $records): array
    {
        return array_map(self::rule(...), $records);
    }

    /** @param array<string, mixed> $record */
    public static function rule(array $record): PricingRule
    {
        $conditions = $record['conditions'] ?? [];
        if (is_string($conditions)) {
            $conditions = json_decode($conditions, true, flags: JSON_THROW_ON_ERROR);
        }
        $codes = $record['basis_charge_codes'] ?? [];
        if (is_string($codes)) {
            $codes = json_decode($codes, true, flags: JSON_THROW_ON_ERROR);
        }
        $comparisons = [];
        $matchesUnsupportedFacts = true;
        foreach ((array) $conditions as $key => $expected) {
            $fact = PricingFact::tryFrom((string) $key);
            if ($fact === null) {
                // Legacy condition evaluation treated an absent fact as null.
                $matchesUnsupportedFacts = $matchesUnsupportedFacts && $expected === null;

                continue;
            }
            if (! is_scalar($expected) && $expected !== null) {
                $matchesUnsupportedFacts = false;

                continue;
            }
            $comparisons[] = new PricingCondition($fact, $expected);
        }

        return new PricingRule(id: $record['rate_rule_id'], chargeTypeId: $record['charge_type_id'], chargeCode: $record['charge_type_code'], title: $record['title'] ?? $record['charge_type_code'], category: ChargeCategory::from($record['category']), method: CalculationMethod::from($record['calculation_method']), basis: PricingBasis::from($record['basis']), priority: (int) $record['priority'], accountingMappingKey: $record['accounting_mapping_key'], rangeFrom: isset($record['range_from']) ? (float) $record['range_from'] : null, rangeTo: isset($record['range_to']) ? (float) $record['range_to'] : null, fixedAmount: isset($record['fixed_amount']) ? (int) $record['fixed_amount'] : null, unitRate: isset($record['unit_rate']) ? (string) $record['unit_rate'] : null, percentageBps: isset($record['percentage_bps']) ? (int) $record['percentage_bps'] : null, minimumAmount: isset($record['minimum_amount']) ? (int) $record['minimum_amount'] : null, maximumAmount: isset($record['maximum_amount']) ? (int) $record['maximum_amount'] : null, roundingMode: AmountRoundingMode::from($record['amount_rounding_mode'] ?? 'NONE'), roundingStep: isset($record['amount_rounding_step']) ? (int) $record['amount_rounding_step'] : null, basisChargeCodes: $codes, conditions: new PricingConditions($comparisons, $matchesUnsupportedFacts), taxable: (bool) ($record['taxable'] ?? true), serviceTariffVersionId: $record['service_tariff_version_id'] ?? null, incrementalStep: $record['incremental_step'] ?? null, incrementalStepKg: $record['incremental_step_kg'] ?? null);
    }

    /** @param array<string, mixed> $facts */
    public static function facts(array $facts): CalculationFacts
    {
        return new CalculationFacts(actualWeightKg: $facts['actual_weight_kg'] ?? null, billableWeightKg: $facts['billable_weight_kg'] ?? null, parcelCount: $facts['parcel_count'] ?? null, declaredValueAmount: $facts['declared_value_amount'] ?? null, codAmount: $facts['cod_amount'] ?? null, insuranceEnabled: $facts['insurance_enabled'] ?? null, codEnabled: $facts['cod_enabled'] ?? null, remoteArea: $facts['remote_area'] ?? null, weightEvidence: $facts['weight_evidence'] ?? null);
    }
}
