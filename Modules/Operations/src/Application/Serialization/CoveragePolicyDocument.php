<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Serialization;

use Modules\Geography\Application\Support\GeoJson;
use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Domain\ValueObjects\CoverageCriterion;
/** Stable API and publication digest document. */
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;

final class CoveragePolicyDocument
{
    public static function policy(CoveragePolicyRecord $row): array
    {
        return [
            'coverage_policy_id' => (string) $row->coverage_policy_id,
            'policy_code' => (string) $row->policy_code,
            'policy_title' => (string) $row->policy_title,
            'published_version_id' => $row->published_version_id ? (string) $row->published_version_id : null,
        ];
    }

    public static function version(CoveragePolicyVersionRecord $row): array
    {
        return [
            'coverage_policy_version_id' => (string) $row->coverage_policy_version_id,
            'coverage_policy_id' => (string) $row->coverage_policy_id,
            'version_number' => (int) $row->version_number,
            'status' => (string) $row->status,
            'effective_from' => $row->effective_from,
            'effective_to' => $row->effective_to,
            'version' => (int) $row->version,
            'rules' => $row->rules->map(self::rule(...))->all(),
        ];
    }

    public static function rule(CoverageRuleRecord $row): array
    {
        $criterion = match ($row->criterion_type) {
            'PROVINCE' => ['criterion_type' => 'PROVINCE', 'province_id' => (string) $row->province_id],
            'CITY' => ['criterion_type' => 'CITY', 'city_id' => (string) $row->city_id],
            'POSTAL_RANGE' => [
                'criterion_type' => 'POSTAL_RANGE',
                'postal_code_from' => (string) $row->postal_code_from,
                'postal_code_to' => (string) $row->postal_code_to,
            ],
            'POLYGON' => [
                'criterion_type' => 'POLYGON',
                'geometry' => $row->geometry_geojson,
            ],
            default => [
                'criterion_type' => 'POINT_RADIUS',
                'center' => ['latitude' => (float) $row->center_latitude, 'longitude' => (float) $row->center_longitude],
                'radius_meters' => (int) $row->radius_meters,
            ],
        };

        return [
            'coverage_rule_id' => (string) $row->coverage_rule_id,
            'target' => (string) $row->target,
            'target_node_id' => (string) $row->target_node_id,
            'priority' => (int) $row->priority,
            'offering_version_id' => $row->offering_version_id ? (string) $row->offering_version_id : null,
            'criterion' => $criterion,
        ];
    }

    public static function criterion(CoverageCriterion $criterion): array
    {
        return match ($criterion->type) {
            CoverageCriterionType::PROVINCE => ['criterion_type' => 'PROVINCE', 'province_id' => $criterion->provinceId],
            CoverageCriterionType::CITY => ['criterion_type' => 'CITY', 'city_id' => $criterion->cityId],
            CoverageCriterionType::POSTAL_RANGE => ['criterion_type' => 'POSTAL_RANGE', 'postal_code_from' => $criterion->postalFrom, 'postal_code_to' => $criterion->postalTo],
            CoverageCriterionType::POLYGON => ['criterion_type' => 'POLYGON', 'geometry' => GeoJson::serialize($criterion->geometry)],
            CoverageCriterionType::POINT_RADIUS => ['criterion_type' => 'POINT_RADIUS', 'center' => ['latitude' => $criterion->center->latitude, 'longitude' => $criterion->center->longitude], 'radius_meters' => $criterion->radiusMeters],
        };
    }
}
