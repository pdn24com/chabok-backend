<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\FreightMatrixRuleCompilerInterface;
use Modules\Pricing\Application\Contracts\TariffMatrixCompilerInterface;
use Modules\Pricing\Application\Dto\PricingRateRuleDraftDto;
use Modules\Pricing\Application\Dto\PricingZoneColumnDto;
use Modules\Pricing\Application\Dto\TariffDraftDto;
use Modules\Pricing\Application\Mappers\FreightMatrixInput;
use Modules\Pricing\Application\Mappers\MatrixRateRuleInput;
use Modules\Pricing\Application\Repositories\PricingChargeTypeRepositoryInterface;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\Enums\PricingValidationCode;
use Modules\Pricing\Domain\Enums\TariffKind;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Exceptions\InvalidTariffMatrix;
use Modules\Pricing\Domain\Policies\ZoneRankPolicy;
use Modules\Pricing\Domain\Validators\FreightMatrixValidator;
use Modules\Pricing\Domain\ValueObjects\PricingValidationIssue;
use Modules\Pricing\Domain\ValueObjects\PricingValidationResult;
use Modules\ServiceCatalog\Application\Contracts\CatalogSelectionInspectorInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogSelectionDto;

final readonly class TariffMatrixCompiler implements TariffMatrixCompilerInterface
{
    public const GLOBAL_COLUMN = '0';

    public function __construct(
        private CatalogSelectionInspectorInterface $catalogSelectionInspector,
        private FreightMatrixValidator $freightMatrixValidator,
        private ZoneRankPolicy $zoneRankPolicy,
        private FreightMatrixRuleCompilerInterface $freightMatrixRuleCompiler,
        private PricingChargeTypeRepositoryInterface $pricingChargeTypeRepository,
    ) {}

    public function prepare(
        TariffDraftDto $input,
        array $zones,
        string $hqId,
    ): TariffDraftDto {
        if ($input->kind === TariffKind::Service) {
            return $this->prepareService($input, $zones);
        }
        $policy = $input->zonePolicy;
        $matrices = $input->freightMatrices;
        $matrixDefinitions = FreightMatrixInput::fromDrafts($matrices);
        $errors = $this->freightMatrixValidator->validate($matrixDefinitions, array_map(static fn (PricingZoneColumnDto $zone) => $zone->id, $zones), $policy);
        $errors = array_map(static fn ($issue): PricingValidationIssue => new PricingValidationIssue($issue->code, $issue->field), $errors);
        if ($policy === null) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::ZONE_POLICY_INVALID, 'zone_policy');
        }
        if ($policy === ZonePolicy::HIGHER_ZONE_RANK && ! $this->zoneRankPolicy->valid(array_map(static fn (PricingZoneColumnDto $zone) => $zone->rank, $zones))) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::ZONE_RANK_INCOMPLETE, 'zone_set_version_id');
        }
        $baseId = (string) $this->pricingChargeTypeRepository->idByCode('BASE_FREIGHT');
        $selections = [];
        foreach ($matrixDefinitions as $matrix) {
            $selections[] = new CatalogSelectionDto($matrix->serviceOfferingVersionId, $matrix->serviceOptionVersionId);
        }
        foreach ($this->catalogSelectionInspector->inspect($hqId, $selections) as $availability) {
            if (! $availability->offeringAvailable) {
                $errors[] = new PricingValidationIssue(PricingValidationCode::SERVICE_VERSION_NOT_PUBLISHED, 'freight_matrices');
            }
            if (! $availability->optionBound) {
                $errors[] = new PricingValidationIssue(PricingValidationCode::SERVICE_OPTION_NOT_BOUND, 'freight_matrices');
            }
        }
        $rules = array_values(array_filter($input->rules, static fn (PricingRateRuleDraftDto $rule): bool => $rule->matrixCellId === null || $rule->matrixCellId === ''));
        foreach ($rules as $rule) {
            if ($rule->chargeTypeId !== $baseId) {
                continue;
            }
            if ($policy === ZonePolicy::HIGHER_ZONE_RANK) {
                $errors[] = new PricingValidationIssue(PricingValidationCode::RANK_REQUIRES_MATRIX, 'rules');
            }
            foreach ($matrices as $matrix) {
                if ($matrix->hasSameCatalogSelection($rule->serviceOfferingVersionId, $rule->serviceOptionVersionId)) {
                    $errors[] = new PricingValidationIssue(PricingValidationCode::MATRIX_RULE_CONFLICT, 'rules');
                }
            }
        }
        if ($errors) {
            throw new InvalidTariffMatrix(new PricingValidationResult($errors), 'pricing.tariff_matrix_validation_failed');
        }

        $input->zonePolicy = $policy;
        $input->rules = [...$rules, ...MatrixRateRuleInput::drafts($this->freightMatrixRuleCompiler->compile($matrixDefinitions, $baseId))];

        return $input;
    }

    private function prepareService(TariffDraftDto $input, array $zones): TariffDraftDto
    {
        $charge = $this->pricingChargeTypeRepository->findActive($input->serviceChargeTypeId);
        if (! $charge || $charge->category !== 'SURCHARGE') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.service_charge_type_is_invalid');
        }
        $basis = $input->matrixBasis ?? PricingBasis::DECLARED_VALUE;
        if (! in_array($basis, [PricingBasis::ACTUAL_WEIGHT, PricingBasis::BILLABLE_WEIGHT, PricingBasis::PARCEL_COUNT, PricingBasis::DECLARED_VALUE, PricingBasis::COD_AMOUNT], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.matrix_basis_is_invalid');
        }
        $global = empty($input->zoneSetVersionId);
        $policy = $global ? ZonePolicy::HIGHER_ZONE_RANK : $input->zonePolicy;
        $matrices = $input->freightMatrices;
        $zoneIds = $global ? [self::GLOBAL_COLUMN] : array_map(static fn (PricingZoneColumnDto $zone) => $zone->id, $zones);
        foreach ($matrices as $matrix) {
            $matrix->serviceOfferingVersionId = null;
            $matrix->serviceOptionVersionId = null;
        }
        $matrixDefinitions = FreightMatrixInput::fromDrafts($matrices);
        $errors = $this->freightMatrixValidator->validate($matrixDefinitions, $zoneIds, $policy);
        $errors = array_map(static fn ($issue): PricingValidationIssue => new PricingValidationIssue($issue->code, $issue->field), $errors);
        if (! $global && $policy === ZonePolicy::HIGHER_ZONE_RANK && ! $this->zoneRankPolicy->valid(array_map(static fn (PricingZoneColumnDto $zone) => $zone->rank, $zones))) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::ZONE_RANK_INCOMPLETE, 'zone_set_version_id');
        }
        if (! empty($input->serviceTariffFamilyIds) || ! empty($input->rules) || ! empty($input->isDefault)) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::SERVICE_COMPOSITION_INVALID, 'rules');
        }
        if ($errors) {
            throw new InvalidTariffMatrix(new PricingValidationResult($errors), 'pricing.service_matrix_is_invalid');
        }
        $rules = MatrixRateRuleInput::drafts($this->freightMatrixRuleCompiler->compile($matrixDefinitions, (string) $charge->charge_type_id));
        foreach ($rules as $rule) {
            $rule->basis = $basis;
            if ($global) {
                $rule->destinationZoneId = null;
            }
            if ($rule->incrementalStepKg !== null) {
                $rule->incrementalStep = $rule->incrementalStepKg;
                $rule->incrementalStepKg = null;
            }
            $rule->priority = 10;
            $rule->conditions = match ($charge->code) {
                'INSURANCE', 'INSURANCE_FEE' => ['insurance_enabled' => true],
                'COD_FEE' => ['cod_enabled' => true],
                default => [],
            };
        }

        $input->zonePolicy = $policy;
        $input->matrixBasis = $basis;
        $input->freightMatrices = $matrices;
        $input->rules = $rules;

        return $input;
    }
}
