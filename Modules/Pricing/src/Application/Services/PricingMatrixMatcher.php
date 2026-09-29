<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingMatrixMatcherInterface;
use Modules\Pricing\Application\Dto\TariffMatrixSelectionDto;
use Modules\Pricing\Domain\Enums\MatrixCellState;
use Modules\ServiceCatalog\Application\Contracts\CatalogResolverInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

final readonly class PricingMatrixMatcher implements PricingMatrixMatcherInterface
{
    public function __construct(private CatalogResolverInterface $catalogResolver) {}

    /** @param list<string> $selectedOptionVersionIds */
    public function matrixCell(
        TariffMatrixSelectionDto $tariff,
        string $offeringId,
        array $selectedOptionVersionIds,
        string $originCode,
        string $basisCode,
        float $weight,
    ): ?string {
        $matrices = $tariff->matrices;
        if ($matrices === []) {
            return null;
        }
        $codes = $tariff->zoneCodes;
        $serviceTariff = $tariff->serviceTariff;
        $offeringVersions = $serviceTariff ? [] : $this->catalogResolver->relatedVersions(CatalogResource::Offering, $offeringId);
        $optionReferences = [];
        foreach ($matrices as $matrix) {
            if (($serviceTariff || in_array($matrix->serviceOfferingVersionId, $offeringVersions, true)) && ! empty($matrix->serviceOptionVersionId)) {
                $optionReferences[] = $matrix->serviceOptionVersionId;
            }
        }
        $optionVersions = [];
        foreach ($this->catalogResolver->optionRevisions(array_values(array_unique($optionReferences))) as $option) {
            $optionVersions[$option->reference] = $option->versionIds;
        }
        $candidates = [];
        foreach ($matrices as $matrix) {
            if (! $serviceTariff && ! in_array($matrix->serviceOfferingVersionId, $offeringVersions, true)) {
                continue;
            }
            if ($matrix->serviceOptionVersionId !== null && $matrix->serviceOptionVersionId !== '') {
                if (array_intersect($optionVersions[$matrix->serviceOptionVersionId], $selectedOptionVersionIds) === []) {
                    continue;
                }
            }
            if (! empty($matrix->originZoneId) && ($codes[$matrix->originZoneId] ?? null) !== $originCode) {
                continue;
            }
            $candidates[] = $matrix;
        }
        $specific = array_values(array_filter($candidates, static fn ($m) => ! empty($m->serviceOptionVersionId)));
        if ($specific !== []) {
            $candidates = $specific;
        }
        if ($candidates === []) {
            return null;
        }
        if (count($candidates) !== 1) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'pricing.more_than_one_freight_matrix_applies', details: ['reason_code' => 'PRICING_MATRIX_AMBIGUOUS']);
        }
        $bands = $candidates[0]->bands;
        $bands = [...$bands, ...$candidates[0]->linearBands];
        foreach ($bands as $band) {
            if ($weight < (float) $band->from || $band->to !== null && $weight >= (float) $band->to) {
                continue;
            }
            foreach ($band->cells as $cell) {
                if (($codes[$cell->zoneId] ?? null) !== $basisCode) {
                    continue;
                }
                if ($cell->state === MatrixCellState::RATE) {
                    return $cell->id;
                }
                throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'pricing.selected_freight_lane_is_not_priced', details: [
                    'reason_code' => $cell->state === MatrixCellState::UNCOVERED ? 'PRICING_LANE_UNCOVERED' : 'PRICING_MATRIX_RATE_MISSING',
                ]);
            }
        }
        throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'pricing.shipment_is_outside_declared_freight_matrix', details: ['reason_code' => 'PRICING_MATRIX_OUTSIDE_DOMAIN']);
    }
}
