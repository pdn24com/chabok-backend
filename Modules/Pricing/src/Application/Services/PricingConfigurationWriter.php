<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Pricing\Application\Contracts\PricingConfigurationWriterInterface;
use Modules\Pricing\Application\Dto\PricingRateRuleDraftDto;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Application\Serialization\PricingCalculationSerializer;
use Modules\Pricing\Domain\ValueObjects\CalculationLine;

final readonly class PricingConfigurationWriter implements PricingConfigurationWriterInterface
{
    public function __construct(
        private TariffRepositoryInterface $tariffRepository,
        private PricingQuoteRepositoryInterface $pricingQuoteRepository,
    ) {}

    /** @param list<PricingRateRuleDraftDto> $rules */
    public function replaceRules(string $versionId, array $rules): void
    {
        $this->tariffRepository->deleteRules($versionId);
        $rows = [];
        foreach ($rules as $rule) {
            $rows[] = [

                'matrix_cell_id' => $rule->matrixCellId,
                'taxable' => $rule->taxable,
                'tariff_version_id' => $versionId,
                'service_offering_version_id' => $rule->serviceOfferingVersionId,
                'service_option_version_id' => $rule->serviceOptionVersionId,
                'charge_type_id' => $rule->chargeTypeId,
                'origin_zone_id' => $rule->originZoneId,
                'destination_zone_id' => $rule->destinationZoneId,
                'calculation_method' => $rule->calculationMethod->value,
                'basis' => $rule->basis->value,
                'range_from' => $rule->rangeFrom,
                'range_to' => $rule->rangeTo,
                'fixed_amount' => $rule->fixedAmount,
                'unit_rate' => $rule->unitRate,
                'incremental_step_kg' => $rule->incrementalStepKg,
                'incremental_step' => $rule->incrementalStep,
                'percentage_bps' => $rule->percentageBps,
                'minimum_amount' => $rule->minimumAmount,
                'maximum_amount' => $rule->maximumAmount,
                'amount_rounding_mode' => $rule->amountRoundingMode->value,
                'amount_rounding_step' => $rule->amountRoundingStep,
                'basis_charge_codes' => $rule->basisChargeCodes,
                'conditions' => $rule->conditions,
                'priority' => $rule->priority,
            ];
        }
        $this->tariffRepository->insertRules($rows);
    }

    /** @param list<CalculationLine> $lines */
    public function insertLines(string $parentId, array $lines): void
    {
        $rows = [];
        foreach ($lines as $index => $line) {
            $rows[] = [

                'quote_id' => $parentId,
                'line_number' => $index + 1,
                'charge_type_id' => $line->rule->chargeTypeId,
                'rate_rule_id' => $line->rule->id,
                'charge_type_code' => $line->rule->chargeCode,
                'title' => $line->rule->title,
                'calculation_method' => $line->rule->method->value,
                'basis' => $line->rule->basis->value,
                'quantity' => $line->quantity,
                'unit_rate' => $line->rule->unitRate,
                'amount' => $line->amount,
                'accounting_mapping_key' => $line->rule->accountingMappingKey,
                'explanation' => PricingCalculationSerializer::explanation($line),
            ];
        }
        $this->pricingQuoteRepository->insertQuoteLines($rows);
    }
}
