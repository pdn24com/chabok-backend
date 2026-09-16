<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingInput
{
    public function __construct(
        private \Modules\Geography\Application\GeographyResolver $geography,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function normalize(array $input): array
    {
        if (!(bool) ($input['insurance_enabled'] ?? false)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Declared-value insurance is mandatory.', fieldErrors: ['insurance_enabled' => ['Insurance is required.']]);
        }
        $packages = $input['parcels'] ?? [];
        if ($packages === []) {
            $packages = [$input];
        }
        foreach ($packages as $index => $package) {
            if (!isset($package['weight_kg']) || !is_numeric($package['weight_kg']) || !is_finite((float) $package['weight_kg']) || $package['weight_kg'] <= 0) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'A positive parcel weight is required.', fieldErrors: ["parcels.{$index}.weight_kg" => ['Positive weight required.']]);
            }
            $dimensions = array_filter(array_intersect_key($package, array_flip(['length_cm', 'width_cm', 'height_cm'])), fn($v) => $v !== null);
            if ($dimensions !== [] && (count($dimensions) !== 3 || count(array_filter($dimensions, fn($v) => is_numeric($v) && is_finite((float) $v) && $v > 0)) !== 3)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Provide all three positive parcel dimensions or omit them together.', fieldErrors: ["parcels.{$index}" => ['Incomplete or invalid dimensions.']]);
            }
        }
        if (($input['cod_enabled'] ?? false) && (int) ($input['cod_amount'] ?? 0) <= 0) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'COD requires a positive goods collection amount.');
        }
        unset($input['_hq_id'], $input['_node_id'], $input['_actor_user_id'], $input['_actor_session_id'], $input['_pricing_request_id']);
        foreach (['sender', 'receiver'] as $party) {
            $input[$party] = $this->geography->canonicalizeContact((array) ($input[$party] ?? []), false);
            unset($input[$party]['city_reference']);
        }
        $input['purpose'] = $input['purpose'] ?? 'SALES';
        $input['channel'] = $input['channel'] ?? 'BRANCH';
        $input['as_of_timestamp'] = CarbonImmutable::parse((string) ($input['as_of_timestamp'] ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc()->toISOString();
        $input['acceptance_at'] = $input['acceptance_at'] ?? $input['as_of_timestamp'];
        $input['selected_option_version_ids'] = array_values((array) ($input['selected_option_version_ids'] ?? []));
        return $input;
    }

    public function fingerprint(array $value): string
    {
        $sort = function (&$item) use (&$sort) {
            if (is_array($item)) {
                if (!array_is_list($item)) {
                    ksort($item);
                }
                foreach ($item as &$child) {
                    $sort($child);
                }
            }
        };
        $sort($value);
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE));
    }

    public function databaseTimestamp(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }
}
