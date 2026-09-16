<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingFacts
{
    public function facts(array $input, array $tariff, array $destination): array
    {
        $parcels = array_values(array_filter((array) ($input['parcels'] ?? []), fn($p) => isset($p['weight_kg'])));
        $actual = 0.0;
        $billable = 0.0;
        $step = (float) $tariff['weight_rounding_step_kg'];
        $divisor = (float) $tariff['volumetric_divisor'];
        if ($parcels !== []) {
            foreach ($parcels as $parcel) {
                $weight = (float) $parcel['weight_kg'];
                $volume = isset($parcel['length_cm'], $parcel['width_cm'], $parcel['height_cm']) ? (float) $parcel['length_cm'] * (float) $parcel['width_cm'] * (float) $parcel['height_cm'] / $divisor : 0;
                $actual += $weight;
                $billable += $this->roundWeight(max($weight, $volume), $step, (string) $tariff['rounding_mode']);
            }
            $evidence = 'PER_PARCEL';
        } else {
            if (!isset($input['weight_kg'])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Parcel or aggregate weight is required.');
            }
            $actual = (float) $input['weight_kg'];
            $volume = isset($input['length_cm'], $input['width_cm'], $input['height_cm']) ? (float) $input['length_cm'] * (float) $input['width_cm'] * (float) $input['height_cm'] / $divisor : 0;
            $billable = $this->roundWeight(max($actual, $volume), $step, (string) $tariff['rounding_mode']);
            $evidence = 'AGGREGATE_FALLBACK';
        }
        return [
            'actual_weight_kg' => $actual,
            'billable_weight_kg' => $billable,
            'parcel_count' => max(1, count($parcels)),
            'declared_value_amount' => (int) ($input['declared_value_amount'] ?? 0),
            'cod_amount' => (int) ($input['cod_amount'] ?? 0),
            'insurance_enabled' => (bool) ($input['insurance_enabled'] ?? false),
            'cod_enabled' => (bool) ($input['cod_enabled'] ?? false),
            'remote_area' => (bool) $destination['remote_area'],
            'weight_evidence' => $evidence,
        ];
    }

    public function roundWeight(float $weight, float $step, string $mode): float
    {
        $units = $weight / $step;
        $rounded = match ($mode) {
            'HALF_UP' => round($units, 0, PHP_ROUND_HALF_UP),
            'HALF_EVEN' => round($units, 0, PHP_ROUND_HALF_EVEN),
            'FLOOR' => floor($units),
            default => ceil($units),
        };
        return max($step, $rounded * $step);
    }
}
