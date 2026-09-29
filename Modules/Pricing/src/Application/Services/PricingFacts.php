<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Pricing\Application\Contracts\PricingFactsInterface;
use Modules\Pricing\Domain\Exceptions\InvalidPricingInput;
use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\ShipmentMeasurements;
use Modules\Pricing\Domain\ValueObjects\WeightPricingPolicy;

final readonly class PricingFacts implements PricingFactsInterface
{
    public function facts(ShipmentMeasurements $shipment, WeightPricingPolicy $policy, bool $remoteArea): CalculationFacts
    {
        $perParcel = $shipment->parcels !== [];
        $parcels = $perParcel ? $shipment->parcels : ($shipment->aggregate === null ? [] : [$shipment->aggregate]);
        if ($parcels === []) {
            throw new InvalidPricingInput('pricing.parcel_or_aggregate_weight_is_required');
        }
        $actual = 0.0;
        $billable = 0.0;
        foreach ($parcels as $parcel) {
            $actual += $parcel->weightKg;
            $billable += $policy->round(max($parcel->weightKg, $parcel->volumetricWeight($policy->volumetricDivisor)));
        }

        return new CalculationFacts(actualWeightKg: $actual, billableWeightKg: $billable, parcelCount: count($parcels),
            declaredValueAmount: $shipment->declaredValueAmount, codAmount: $shipment->codAmount,
            insuranceEnabled: $shipment->insuranceEnabled, codEnabled: $shipment->codEnabled, remoteArea: $remoteArea,
            weightEvidence: $perParcel ? 'PER_PARCEL' : 'AGGREGATE_FALLBACK');
    }
}
