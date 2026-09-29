<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\PricingBasis;

/** Working rule definition, normalized by the freight/service compiler before persistence. */
final class PricingRateRuleDraftDto
{
    /** @param list<string>|null $basisChargeCodes @param array<string, mixed>|null $conditions */
    public function __construct(public string $chargeTypeId, public CalculationMethod $calculationMethod,
        public ?string $serviceOfferingVersionId = null,
        public ?string $serviceOptionVersionId = null,
        public ?string $matrixCellId = null,
        public ?string $originZoneId = null,
        public ?string $destinationZoneId = null,
        public PricingBasis $basis = PricingBasis::BILLABLE_WEIGHT,
        public ?string $rangeFrom = null,
        public ?string $rangeTo = null,
        public ?int $fixedAmount = null,
        public ?string $unitRate = null,
        public ?string $incrementalStepKg = null,
        public ?string $incrementalStep = null,
        public ?int $percentageBps = null,
        public ?int $minimumAmount = null,
        public ?int $maximumAmount = null,
        public AmountRoundingMode $amountRoundingMode = AmountRoundingMode::NONE,
        public ?int $amountRoundingStep = null,
        public ?array $basisChargeCodes = null,
        public ?array $conditions = null,
        public ?bool $taxable = null,
        public int $priority = 100) {}

    public static function fromInput(array $input): self
    {
        return new self(chargeTypeId: $input['charge_type_id'], calculationMethod: CalculationMethod::from($input['calculation_method']),
            serviceOfferingVersionId: isset($input['service_offering_version_id']) ? (string) $input['service_offering_version_id'] : null,
            serviceOptionVersionId: isset($input['service_option_version_id']) ? (string) $input['service_option_version_id'] : null,
            matrixCellId: isset($input['matrix_cell_id']) ? (string) $input['matrix_cell_id'] : null,
            originZoneId: isset($input['origin_zone_id']) ? (string) $input['origin_zone_id'] : null,
            destinationZoneId: isset($input['destination_zone_id']) ? (string) $input['destination_zone_id'] : null,
            basis: isset($input['basis']) ? PricingBasis::from($input['basis']) : PricingBasis::BILLABLE_WEIGHT,
            rangeFrom: isset($input['range_from']) ? (string) $input['range_from'] : null,
            rangeTo: isset($input['range_to']) ? (string) $input['range_to'] : null,
            fixedAmount: isset($input['fixed_amount']) ? (int) $input['fixed_amount'] : null,
            unitRate: isset($input['unit_rate']) ? (string) $input['unit_rate'] : null,
            incrementalStepKg: isset($input['incremental_step_kg']) ? (string) $input['incremental_step_kg'] : null,
            incrementalStep: isset($input['incremental_step']) ? (string) $input['incremental_step'] : null,
            percentageBps: isset($input['percentage_bps']) ? (int) $input['percentage_bps'] : null,
            minimumAmount: isset($input['minimum_amount']) ? (int) $input['minimum_amount'] : null,
            maximumAmount: isset($input['maximum_amount']) ? (int) $input['maximum_amount'] : null,
            amountRoundingMode: isset($input['amount_rounding_mode']) ? AmountRoundingMode::from($input['amount_rounding_mode']) : AmountRoundingMode::NONE,
            amountRoundingStep: isset($input['amount_rounding_step']) ? (int) $input['amount_rounding_step'] : null,
            basisChargeCodes: $input['basis_charge_codes'] ?? null,
            conditions: $input['conditions'] ?? null,
            taxable: isset($input['taxable']) ? (bool) $input['taxable'] : null,
            priority: (int) ($input['priority'] ?? 100),
        );
    }
}
