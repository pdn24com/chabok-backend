<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Geography\Domain\GeoJsonGeometry;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CoverageRuleGuard
{
    public function __construct(private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage)
    {
    }

    public function validateRuleInputs(string $hq, array $rules): void
    {
        if ($rules === []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'At least one Coverage Rule is required.');
        }
        $signatures = [];
        foreach ($rules as $rule) {
            if (!in_array($rule['target'] ?? null, ['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE'], true) || !isset($rule['target_node_id'], $rule['priority'], $rule['criterion']['criterion_type'])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every Coverage Rule is incomplete.');
            }
            if ((int) $rule['priority'] < -100000 || (int) $rule['priority'] > 100000) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Coverage priority is outside the supported range.');
            }
            if (!$this->coverage->activeNode($hq, $rule['target_node_id'])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every target Node must be active and belong to the current HQ.');
            }
            if (($rule['offering_version_id'] ?? null) !== null && !$this->coverage->offeringVisible($hq, $rule['offering_version_id'])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Offering Version is not visible to this HQ.');
            }
            $criterion = $this->normalizeCriterion($rule['criterion']);
            $signature = hash('sha256', json_encode([$rule['target'], (int) $rule['priority'], $rule['offering_version_id'] ?? null, $criterion], JSON_THROW_ON_ERROR));
            if (isset($signatures[$signature])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Duplicate indistinguishable Coverage Rules are not allowed.');
            }
            $signatures[$signature] = true;
        }
    }

    public function normalizeCriterion(array $criterion): array
    {
        $type = $criterion['criterion_type'] ?? null;
        if ($type === 'PROVINCE' && isset($criterion['province_id']) && $this->coverage->provinceExists($criterion['province_id'])) {
            return ['criterion_type' => $type, 'province_id' => $criterion['province_id']];
        }
        if ($type === 'CITY' && isset($criterion['city_id']) && $this->coverage->cityExists($criterion['city_id'])) {
            return ['criterion_type' => $type, 'city_id' => $criterion['city_id']];
        }
        if ($type === 'POSTAL_RANGE' && preg_match('/^\d{10}$/', (string) ($criterion['postal_code_from'] ?? '')) && preg_match('/^\d{10}$/', (string) ($criterion['postal_code_to'] ?? '')) && strcmp($criterion['postal_code_from'], $criterion['postal_code_to']) <= 0) {
            return [
                'criterion_type' => $type,
                'postal_code_from' => $criterion['postal_code_from'],
                'postal_code_to' => $criterion['postal_code_to'],
            ];
        }
        if ($type === 'POLYGON' && is_array($criterion['geometry'] ?? null)) {
            return ['criterion_type' => $type, 'geometry' => GeoJsonGeometry::normalize($criterion['geometry'])];
        }
        if ($type === 'POINT_RADIUS' && isset($criterion['center']['latitude'], $criterion['center']['longitude'], $criterion['radius_meters']) && $criterion['center']['latitude'] >= -90 && $criterion['center']['latitude'] <= 90 && $criterion['center']['longitude'] >= -180 && $criterion['center']['longitude'] <= 180 && $criterion['radius_meters'] >= 1 && $criterion['radius_meters'] <= 500000) {
            return [
                'criterion_type' => $type,
                'center' => [
                    'latitude' => (float) $criterion['center']['latitude'],
                    'longitude' => (float) $criterion['center']['longitude'],
                ],
                'radius_meters' => (int) $criterion['radius_meters'],
            ];
        }
        throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Coverage criterion is invalid.');
    }
}
