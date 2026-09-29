<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Mappers\CoverageAddressInput;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingFactsInterface;
use Modules\Pricing\Application\Contracts\PricingInputInterface;
use Modules\Pricing\Application\Contracts\PricingMatrixMatcherInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingRuleCalculatorInterface;
use Modules\Pricing\Application\Contracts\PricingZoneResolverInterface;
use Modules\Pricing\Application\Contracts\QuoteCalculatorInterface;
use Modules\Pricing\Application\Contracts\QuoteWriterInterface;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Dto\CalculatedQuoteDto;
use Modules\Pricing\Application\Dto\PricingSimulationDto;
use Modules\Pricing\Application\Dto\QuoteInputDto;
use Modules\Pricing\Application\Dto\QuoteResolutionDto;
use Modules\Pricing\Application\Dto\ServiceTariffEvidenceDto;
use Modules\Pricing\Application\Dto\TariffMatrixSelectionDto;
use Modules\Pricing\Application\Mappers\FreightMatrixInput;
use Modules\Pricing\Application\Mappers\PricingCalculationInput;
use Modules\Pricing\Application\Mappers\PricingFactInput;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Application\Serialization\QuoteEvidenceDocument;
use Modules\Pricing\Application\Serialization\QuoteInputDocument;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Policies\ZoneRankPolicy;
use Modules\Pricing\Domain\ValueObjects\CalculationResult;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffRateRuleRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Modules\ServiceCatalog\Application\Contracts\CatalogResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolverInterface;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

final readonly class QuoteCalculator implements QuoteCalculatorInterface
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private QuoteWriterInterface $quoteWriter,
        private PricingInputInterface $pricingInput,
        private PricingReaderInterface $pricingReader,
        private ServiceEligibilityResolverInterface $serviceEligibilityResolver,
        private CatalogResolverInterface $catalogResolver,
        private PricingZoneResolverInterface $pricingZoneResolver,
        private PricingFactsInterface $pricingFacts,
        private ZoneRankPolicy $zoneRankPolicy,
        private PricingMatrixMatcherInterface $pricingMatrixMatcher,
        private ServiceTariffDependenciesInterface $serviceTariffDependencies,
        private PricingRuleCalculatorInterface $pricingRuleCalculator,
        private ClockInterface $clock,
        private TariffRepositoryInterface $tariffRepository,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
        private PricingQuoteRepositoryInterface $pricingQuoteRepository,
    ) {}

    public function calculate(
        AuthenticatedPrincipal $actor,
        QuoteInputDto $input,
        string $idempotencyKey,
        ?TariffVersionRecord $draft = null,
    ): PricingQuoteRecord|PricingSimulationDto {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        $purpose = (string) ($input->purpose ?? 'SALES');
        if ($purpose !== 'SALES') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.pricing_purpose_is_not_active_milestone_1', details: ['reason_code' => 'PRICING_PURPOSE_NOT_ACTIVE']);
        }
        $input = $this->pricingInput->normalize($input);
        $inputFingerprint = $this->pricingInput->fingerprint($input);
        $existing = $draft === null ? $this->pricingQuoteRepository->findByIdempotencyKey($actor->hqId, $actor->userId, $idempotencyKey) : null;
        if ($existing !== null) {
            if ((string) $existing->input_fingerprint !== $inputFingerprint) {
                throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'pricing.idempotency_key_already_used_with_different_pricing');
            }

            return $this->pricingReader->quoteDetail($actor, (string) $existing->quote_id);
        }
        $resolution = $this->resolve($actor, $input, $draft);
        $calculation = $this->pricingRuleCalculator->calculate($resolution->calculationRules, $resolution->facts);
        $this->assertCompletePricing($input, $calculation);
        $now = CarbonImmutable::instance($this->clock->now())->utc();
        $warnings = $resolution->facts->weightEvidence === 'AGGREGATE_FALLBACK' ? ['PRICING_AGGREGATE_WEIGHT_FALLBACK'] : [];
        if ($draft !== null) {
            return new PricingSimulationDto($calculation, $resolution->tariff, $resolution->resolvedZoneSetVersionId, $now, QuoteEvidenceDocument::serialize($resolution), $warnings);
        }

        return $this->quoteWriter->write($actor, $idempotencyKey, new CalculatedQuoteDto($input, $resolution, $calculation, $inputFingerprint, $now, $warnings));
    }

    private function resolve(AuthenticatedPrincipal $actor, QuoteInputDto $input, ?TariffVersionRecord $draft): QuoteResolutionDto
    {
        $offering = $this->serviceEligibilityResolver->validateSelection($actor, (string) $input->serviceOfferingId, $input->serviceOfferingVersionId, OfferingSelectionInput::fromArray(QuoteInputDocument::quote($input)));
        $offeringReferences = $this->catalogResolver->relatedVersions(CatalogResource::Offering, $offering->offeringId);
        $selectedOptions = $this->catalogResolver->optionRevisions($input->selectedOptionVersionIds, $actor->hqId);
        $optionReferences = [];
        $selectedOptionIdentities = [];
        foreach ($selectedOptions as $option) {
            array_push($optionReferences, ...$option->versionIds);
            // Evidence historically includes selections made through a revision identifier.
            if (array_intersect($option->versionIds, $input->selectedOptionVersionIds) !== []) {
                $selectedOptionIdentities[$option->optionId] = true;
            }
        }
        $asOf = CarbonImmutable::parse((string) $input->asOfTimestamp)->utc();
        $tariff = $draft ?? $this->tariffRepository->findEligibleFreightVersion((string) $actor->hqId, $offeringReferences, $asOf);
        if ($tariff === null) {
            throw new ApiException(ApiErrorCode::PricingTariffNotFound, 422, 'pricing.no_eligible_tariff_found', details: ['reason_code' => 'PRICING_TARIFF_NOT_FOUND']);
        }
        $resolvedZoneSetVersionId = $this->pricingZoneResolver->resolveEffectiveZoneSetVersion((string) $tariff->zone_set_version_id, $asOf);
        $lane = $this->pricingZoneResolver->resolveLane($resolvedZoneSetVersionId, CoverageAddressInput::fromArray(QuoteInputDocument::contact($input->sender)), CoverageAddressInput::fromArray(QuoteInputDocument::contact($input->receiver)));
        $origin = $lane->origin->zone;
        $destination = $lane->destination->zone;
        $facts = $this->pricingFacts->facts(PricingFactInput::shipment($input), PricingFactInput::policy($tariff), (bool) $destination->remote_area);
        $basisZone = $destination;
        $rankedZones = null;
        if ($tariff->zone_policy === ZonePolicy::HIGHER_ZONE_RANK->value) {
            $rankedZones = $this->pricingZoneRepository->zoneRanksOfVersion($resolvedZoneSetVersionId);
            if (! $this->zoneRankPolicy->valid($rankedZones)) {
                throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'pricing.zone_ranks_are_incomplete_ambiguous', details: ['reason_code' => 'PRICING_ZONE_RANK_INCOMPLETE']);
            }
            $basisZone = $origin->rank > $destination->rank ? $origin : $destination;
        }
        $zoneCodes = $this->pricingZoneRepository->zoneCodesOfVersion($tariff->zone_set_version_id);
        $matrixCell = $this->pricingMatrixMatcher->matrixCell(new TariffMatrixSelectionDto(FreightMatrixInput::many($tariff->freight_matrices ?? []), $zoneCodes, false), $offering->offeringId, $input->selectedOptionVersionIds, $origin->code, $basisZone->code, $facts->billableWeightKg);
        $rules = $this->tariffRepository->applicableRules((string) $tariff->tariff_version_id, $offeringReferences, $optionReferences)
            ->filter(static function (TariffRateRuleRecord $rule) use ($matrixCell, $origin, $destination): bool {
                if ($rule->matrix_cell_id !== null) {
                    return $rule->matrix_cell_id === $matrixCell;
                }

                return ($rule->originZone === null || $rule->originZone->code === $origin->code)
                    && ($rule->destinationZone === null || $rule->destinationZone->code === $destination->code);
            });
        if ($rules->isEmpty()) {
            throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'pricing.no_pricing_rule_matches_selected_service_lane', details: ['reason_code' => 'PRICING_RULE_NOT_FOUND']);
        }
        $calculationRules = $rules->map(static fn (TariffRateRuleRecord $rule) => PricingCalculationInput::fromRecord($rule))->values()->all();
        $currentOptionVersions = [];
        foreach ($selectedOptions as $option) {
            if ($option->currentVersionId === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.catalog_dependency_is_inactive_unavailable', details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => 'options']);
            }
            $currentOptionVersions[] = $option->currentVersionId;
        }
        $resolution = new QuoteResolutionDto($tariff, $offering, $lane, $basisZone, $facts, $resolvedZoneSetVersionId,
            $matrixCell, $selectedOptionIdentities, $currentOptionVersions, $calculationRules, $rankedZones ?? null);
        $this->applyServiceTariffs($actor, $input, $asOf, $resolution, $rules->pluck('charge_type_id')->all());

        return $resolution;
    }

    /** @param list<string> $chargeTypeIds */
    private function applyServiceTariffs(AuthenticatedPrincipal $actor, QuoteInputDto $input, CarbonImmutable $asOf, QuoteResolutionDto $resolution, array $chargeTypeIds): void
    {
        $tariff = $resolution->tariff;
        $offering = $resolution->offering;
        $origin = $resolution->lane->origin->zone;
        $destination = $resolution->lane->destination->zone;
        $facts = $resolution->facts;
        $resolvedZoneSetVersionId = $resolution->resolvedZoneSetVersionId;
        $rankedZones = $resolution->zoneRanks;
        $dependencies = $this->serviceTariffDependencies->resolve($this->serviceTariffDependencies->ids($tariff->tariff_version_id), $actor->hqId, $tariff->zone_set_version_id, $asOf, $chargeTypeIds);
        $dependencyIds = [];
        $dependencyZoneIds = [];
        foreach ($dependencies as $dependency) {
            $dependencyIds[] = $dependency->version->tariff_version_id;
            if ($dependency->version->zone_set_version_id !== null) {
                $dependencyZoneIds[] = $dependency->version->zone_set_version_id;
            }
        }
        $dependencyRules = $this->tariffRepository->rulesByVersion($dependencyIds);
        $dependencyZones = $this->pricingZoneRepository->zonesByVersion($dependencyZoneIds);
        foreach ($dependencies as $dependency) {
            $service = $dependency->version;
            $chargeKey = $this->serviceTariffDependencies->chargeKey($dependency->chargeCode);
            $active = ! ($chargeKey === 'INSURANCE' && ! $facts->insuranceEnabled || $chargeKey === 'COD_FEE' && ! $facts->codEnabled);
            $resolution->dependencyEvidence[] = new ServiceTariffEvidenceDto(
                $service->tariff_family_id, $service->tariff_version_id, (int) $service->version_number,
                $dependency->chargeCode, $active, $service->zone_set_version_id ? $resolvedZoneSetVersionId : null,
            );
            if (! $active) {
                continue;
            }
            $quantity = (float) match ($service->matrix_basis) {
                'DECLARED_VALUE' => $facts->declaredValueAmount,
                'COD_AMOUNT' => $facts->codAmount,
                'ACTUAL_WEIGHT' => $facts->actualWeightKg,
                'PARCEL_COUNT' => $facts->parcelCount,
                default => $facts->billableWeightKg,
            };
            $serviceBasisCode = $destination->code;
            if (! $service->zone_set_version_id) {
                $serviceBasisCode = 'GLOBAL';
            } elseif ($service->zone_policy === 'HIGHER_ZONE_RANK') {
                $ranked = $rankedZones ??= $this->pricingZoneRepository->zoneRanksOfVersion($resolvedZoneSetVersionId);
                if (! $this->zoneRankPolicy->valid($ranked)) {
                    throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'pricing.service_zone_ranks_incomplete');
                }
                $serviceBasisCode = $origin->rank > $destination->rank ? $origin->code : $destination->code;
            }
            $serviceCell = $this->pricingMatrixMatcher->matrixCell(new TariffMatrixSelectionDto(FreightMatrixInput::many($service->freight_matrices ?? []), $service->zone_set_version_id === null ? [TariffMatrixCompiler::GLOBAL_COLUMN => 'GLOBAL'] : ($dependencyZones->get($service->zone_set_version_id)?->pluck('code', 'pricing_zone_id')->all() ?? []), true), $offering->offeringId, $input->selectedOptionVersionIds, $origin->code, $serviceBasisCode, $quantity);
            $serviceRules = $dependencyRules->get($service->tariff_version_id, collect())->filter(static fn (TariffRateRuleRecord $rule): bool => $rule->matrix_cell_id === $serviceCell);
            if (count($serviceRules) !== 1) {
                throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'pricing.service_rate_missing_for_range');
            }
            foreach ($serviceRules as $rule) {
                $resolution->calculationRules[] = PricingCalculationInput::fromRecord($rule, $service->tariff_version_id);
            }
        }
    }

    private function assertCompletePricing(QuoteInputDto $input, CalculationResult $calculation): void
    {
        $hasBaseFreight = false;
        $hasInsurance = false;
        foreach ($calculation->lines as $line) {
            $hasBaseFreight = $hasBaseFreight || $line->rule->chargeCode === 'BASE_FREIGHT';
            $hasInsurance = $hasInsurance || in_array($line->rule->chargeCode, ['INSURANCE', 'INSURANCE_FEE'], true);
        }
        if ($calculation->lines === [] || $calculation->totalAmount <= 0 || ! $hasBaseFreight) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'pricing.pricing_did_not_produce_complete_nonzero_base', details: ['reason_code' => 'PRICING_INCOMPLETE_RESULT']);
        }
        if ($input->insuranceEnabled === true && ! $hasInsurance) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'pricing.mandatory_insurance_pricing_is_unavailable', details: ['reason_code' => 'INSURANCE_PRICING_REQUIRED']);
        }
    }
}
