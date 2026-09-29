<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\CalculationLine;
use Modules\Pricing\Domain\ValueObjects\CalculationResult;

/** The persisted quote snapshot and public quote response share this schema. */
final class PricingCalculationSerializer
{
    public static function facts(CalculationFacts $facts): array
    {
        return ['actual_weight_kg' => $facts->actualWeightKg, 'billable_weight_kg' => $facts->billableWeightKg,
            'parcel_count' => $facts->parcelCount, 'declared_value_amount' => $facts->declaredValueAmount,
            'cod_amount' => $facts->codAmount, 'insurance_enabled' => $facts->insuranceEnabled,
            'cod_enabled' => $facts->codEnabled, 'remote_area' => $facts->remoteArea, 'weight_evidence' => $facts->weightEvidence];
    }

    public static function serialize(CalculationResult $result): array
    {
        return [
            'lines' => array_map(self::line(...), $result->lines),
            'subtotal_amount' => $result->subtotalAmount,
            'discount_amount' => $result->discountAmount,
            'tax_amount' => $result->taxAmount,
            'total_amount' => $result->totalAmount,
            'result_fingerprint' => $result->fingerprint,
        ];
    }

    public static function line(CalculationLine $line): array
    {
        $rule = $line->rule;

        return [
            'charge_type_id' => $rule->chargeTypeId,
            'rate_rule_id' => $rule->id,
            'charge_code' => $rule->chargeCode,
            'title' => $rule->title,
            'category' => $rule->category->value,
            'calculation_method' => $rule->method->value,
            'basis' => $rule->basis->value,
            'quantity' => $line->quantity,
            'unit_rate' => $rule->unitRate === null ? null : (float) $rule->unitRate,
            'amount' => $line->amount,
            'accounting_mapping_key' => $rule->accountingMappingKey,
            'taxable' => $rule->taxable,
            'explanation' => self::explanation($line),
        ];
    }

    public static function explanation(CalculationLine $line): array
    {
        $rule = $line->rule;

        return [
            'service_tariff_version_id' => $rule->serviceTariffVersionId,
            'incremental_step' => $rule->incrementalStep,
            'range_from' => $rule->rangeFrom,
            'range_to' => $rule->rangeTo,
            'incremental_step_kg' => $rule->incrementalStepKg,
            'base_amount' => $rule->fixedAmount,
            'declared_value_basis' => $rule->basis === PricingBasis::DECLARED_VALUE ? (int) $line->quantity : null,
            'percentage_bps' => $rule->percentageBps,
            'raw_amount' => $line->rawAmount,
            'amount_rounding_mode' => $rule->roundingMode->value,
            'amount_rounding_step' => $rule->roundingStep,
            'final_amount' => $line->amount,
        ];
    }
}
