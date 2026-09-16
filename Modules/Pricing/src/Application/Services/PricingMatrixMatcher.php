<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Pricing\Application\TariffMatrixCompiler;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingMatrixMatcher
{
    public function __construct(
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\ServiceCatalog\Application\Contracts\CatalogResolver $currentCatalog,
        private \Modules\Pricing\Domain\FreightMatrices $matrices,
    )
    {
    }

    public function matrixCell(object $tariff, array $offering, array $input, array $origin, array $basisZone, float $weight): ?string
    {
        $matrices = json_decode($tariff->freight_matrices ?? '[]', true) ?? [];
        if ($matrices === []) {
            return null;
        }
        $codes = $this->pricing->zoneCodesById($tariff->zone_set_version_id);
        if (!$tariff->zone_set_version_id) {
            $codes = [TariffMatrixCompiler::GLOBAL_COLUMN => 'GLOBAL'];
        }
        $candidates = array_values(array_filter($matrices, function ($m) use ($tariff, $offering, $input, $origin, $codes): bool {
            return (($tariff->tariff_kind ?? 'FREIGHT') === 'SERVICE' || in_array($m['service_offering_version_id'], $this->currentCatalog->relatedVersions('offerings', $offering['service_offering_id']), true)) && (empty($m['service_option_version_id']) || count(array_intersect($this->currentCatalog->relatedVersions('options', $m['service_option_version_id']), $input['selected_option_version_ids'])) > 0) && (empty($m['origin_zone_id']) || ($codes[$m['origin_zone_id']] ?? null) === $origin['code']);
        }));
        $specific = array_values(array_filter($candidates, static fn($m) => !empty($m['service_option_version_id'])));
        if ($specific !== []) {
            $candidates = $specific;
        }
        if ($candidates === []) {
            return null;
        }
        if (count($candidates) !== 1) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'More than one freight matrix applies.', details: ['reason_code' => 'PRICING_MATRIX_AMBIGUOUS']);
        }
        $bands = $candidates[0]['bands'];
        $bands = [...$bands, ...$this->matrices->linearBands($candidates[0])];
        foreach ($bands as $band) {
            if ($weight < (float) $band['from'] || $band['to'] !== null && $weight >= (float) $band['to']) {
                continue;
            }
            foreach ($band['cells'] as $cell) {
                if (($codes[$cell['zone_id']] ?? null) !== $basisZone['code']) {
                    continue;
                }
                if ($cell['state'] === 'RATE') {
                    return $cell['id'];
                }
                throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'The selected freight lane is not priced.', details: ['reason_code' => $cell['state'] === 'UNCOVERED' ? 'PRICING_LANE_UNCOVERED' : 'PRICING_MATRIX_RATE_MISSING']);
            }
        }
        throw new ApiException(ApiErrorCode::PricingRuleNotFound, 422, 'The shipment is outside the declared freight matrix.', details: ['reason_code' => 'PRICING_MATRIX_OUTSIDE_DOMAIN']);
    }
}
