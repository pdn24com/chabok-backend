<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Modules\Pricing\Application\Dto\QuoteInputDto;
use Modules\Pricing\Application\Dto\QuoteParcelDto;
use Modules\Pricing\Domain\Enums\WeightRoundingMode;
use Modules\Pricing\Domain\ValueObjects\ParcelMeasurement;
use Modules\Pricing\Domain\ValueObjects\ShipmentMeasurements;
use Modules\Pricing\Domain\ValueObjects\WeightPricingPolicy;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final class PricingFactInput
{
    public static function shipment(QuoteInputDto $input): ShipmentMeasurements
    {
        $parcels = array_map(self::parcel(...), $input->parcels);

        return new ShipmentMeasurements(parcels: $parcels,
            aggregate: $parcels === [] && $input->weightKg !== null ? new ParcelMeasurement((float) $input->weightKg, $input->lengthCm === null ? null : (float) $input->lengthCm, $input->widthCm === null ? null : (float) $input->widthCm, $input->heightCm === null ? null : (float) $input->heightCm) : null,
            declaredValueAmount: (int) ($input->declaredValueAmount ?? 0), codAmount: (int) ($input->codAmount ?? 0),
            insuranceEnabled: (bool) ($input->insuranceEnabled ?? false), codEnabled: (bool) ($input->codEnabled ?? false));
    }

    public static function policy(TariffVersionRecord $tariff): WeightPricingPolicy
    {
        return new WeightPricingPolicy((float) $tariff->volumetric_divisor, (float) $tariff->weight_rounding_step_kg, WeightRoundingMode::from($tariff->rounding_mode));
    }

    private static function parcel(QuoteParcelDto $parcel): ParcelMeasurement
    {
        return new ParcelMeasurement((float) $parcel->weightKg,
            isset($parcel->lengthCm) ? (float) $parcel->lengthCm : null,
            isset($parcel->widthCm) ? (float) $parcel->widthCm : null,
            isset($parcel->heightCm) ? (float) $parcel->heightCm : null);
    }
}
